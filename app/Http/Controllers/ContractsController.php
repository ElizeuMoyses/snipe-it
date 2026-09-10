<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractAssetLinkException;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Company;
use App\Actions\Contracts\ContractLifecycleAction;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Services\ContractAssetLinkService;
use App\Models\ContractType;
use App\Services\ContractInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use App\Services\Contracts\ContractAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractsController extends Controller
{
    public function __construct(private readonly ContractAssetLinkService $assetLinkService)
    {
    }

    /**
     * Display the contracts dashboard.
     */
    public function dashboard(): View
    {
        $this->authorize('view', Contract::class);

        // Cards de resumo
        $activeCount = Contract::active()->count();
        $expiringSoonCount = Contract::active()->expiringSoon(30)->count();
        $overdueCount = ContractInstallment::overdue()->count();

        $pendingMonthCount = ContractInstallment::pending()
            ->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year)
            ->count();

        $monthlyCommitted = ContractInstallment::pending()
            ->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year)
            ->sum('expected_value');

        $monthlyPaid = ContractInstallment::paid()
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('paid_value');

        // Tabela: próximos vencimentos (15 dias)
        $upcomingInstallments = ContractInstallment::with(['contract.supplier', 'statusLabel'])
            ->pending()
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays(15)->endOfDay())
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        // Tabela: parcelas em atraso (mais antigas primeiro)
        $overdueInstallments = ContractInstallment::with(['contract.supplier', 'statusLabel'])
            ->overdue()
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        return view('contracts.dashboard', compact(
            'activeCount',
            'expiringSoonCount',
            'overdueCount',
            'pendingMonthCount',
            'monthlyCommitted',
            'monthlyPaid',
            'upcomingInstallments',
            'overdueInstallments',
        ));
    }

    /**
     * Show a list of all contracts
     */
    public function index(): View
    {
        $this->authorize('view', Contract::class);

        return view('contracts/index');
    }

    /**
     * Contract create.
     */
    public function create(): View
    {
        $this->authorize('create', Contract::class);

        return view('contracts/edit')
            ->with('item', new Contract)
            ->with('contractTypes', ContractType::active()->orderBy('name')->get());
    }

    /**
     * Calculate a preview using the same calendar and cent arithmetic as
     * persisted installment generation.
     */
    public function preview(Request $request): JsonResponse
    {
        abort_unless(
            Gate::allows('create', Contract::class) || Gate::allows('update', Contract::class),
            403
        );

        try {
            $contract = ContractInput::validatePreview($request->all());

            return response()->json(['data' => $contract->installmentPreview()]);
        } catch (ValidationException $exception) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $exception->errors()], 422);
        }
    }

    /**
     * Contract create form processing.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        try {
            $data = ContractInput::validate($request->all(), true);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        $contract = new Contract;
        ContractInput::apply($contract, $data);
        $contract->created_by = auth()->id();

        try {
            DB::transaction(function () use ($contract, $request) {
                if (! $contract->save()) {
                    throw new \RuntimeException('Contract creation failed.');
                }

                app(ContractAuditService::class)->record($contract, 'contract.created', $contract, [], app(ContractAuditService::class)->snapshot($contract));
                if ($request->has('auto_generate_installments')) {
                    $contract->generateInstallments();
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()->withInput()->withErrors([
                'contract' => trans('admin/contracts/message.create.error'),
            ]);
        }

        return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.create.success'));
    }

    /**
     * Contract update.
     */
    public function edit(Contract $contract): View|RedirectResponse
    {
        $this->authorize('update', $contract);

        return view('contracts/edit')
            ->with('item', $contract)
            ->with('contractTypes', ContractType::withTrashed()
                ->where(function ($query) use ($contract) {
                $query->where('is_active', true)->whereNull('deleted_at')
                        ->orWhere('id', $contract->contract_type_id);
                })
                ->orderBy('name')
                ->get());
    }

    /**
     * Contract update form processing page.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        try {
            return DB::transaction(function () use ($request, $contract) {
                $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
                $this->authorize('update', $contract);
                $audit = app(ContractAuditService::class);
                $before = $audit->snapshot($contract);
                $data = ContractInput::validate($request->all(), false, $contract);
                ContractInput::apply($contract, $data);
                $changed = $contract->isDirty();
                if (! $contract->save()) {
                    return redirect()->back()->withInput()->withErrors($contract->getErrors());
                }
                if ($changed) {
                    $audit->record($contract, 'contract.updated', $contract, $before, $audit->snapshot($contract));
                }
                return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.update.success'));
            });
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }
    }
    /**
     * Delete the given contract.
     */
    public function destroy(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $result = app(ContractLifecycleAction::class)->archive($contract, $validated['reason']);

        return match ($result['status']) {
            'archived' => redirect()->route('contracts.index')
                ->with('success', trans('admin/contracts/message.archive.success')),
            'blocked_paid' => redirect()->route('contracts.index')
                ->with('error', trans('admin/contracts/message.archive.blocked_paid', [
                    'count' => $result['paid_count'],
                ])),
            'already_archived' => redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.archive.already_archived')),
            default => redirect()->route('contracts.index')
                ->with('error', trans('admin/contracts/message.archive.error')),
        };
    }

    /**
     * Restore an archived contract without recreating dependent records.
     */
    public function restore(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('restore', $contract);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'min:3', 'max:2000'],
        ]);

        $result = app(ContractLifecycleAction::class)->restore($contract, $validated['reason'] ?? null);

        return match ($result['status']) {
            'restored' => redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.archive.restored')),
            'restore_conflict' => redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.archive.restore_conflict')),
            'not_archived' => redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.archive.not_archived')),
            default => redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.archive.error')),
        };
    }

    /**
     * Get the contract information to present to the contract view page.
     */
    public function show(Contract $contract): View|RedirectResponse
    {
        $this->authorize('view', $contract);

        $contract->load(['contractType', 'installments.statusLabel', 'installments.adminuser', 'installments.uploads.adminuser', 'amendments.adminuser', 'assets.model', 'assets.assetstatus']);
        $auditHistoryCount = app(ContractAuditService::class)->historyFor($contract, [], 0, 1)['total'];

        $contractPreview = null;
        try { $contractPreview = $contract->installmentPreview(); } catch (\InvalidArgumentException) {}
        return view('contracts/view', compact('contract', 'auditHistoryCount', 'contractPreview'));
    }

    /**
     * Return the authorized, paginated unified contract history for the UI table.
     */
    public function history(Request $request, $contractId): JsonResponse
    {
        $contract = Contract::withTrashed()->findOrFail($contractId);
        $this->authorize('history', $contract);

        $validated = $request->validate([
            'action' => 'nullable|string|max:80',
            'action_type' => 'nullable|string|max:80',
            'entity' => 'nullable|string|max:80',
            'created_by' => 'nullable|integer|min:1',
            'source' => 'nullable|string|max:20',
            'search' => 'nullable|string|max:255',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $page = app(ContractAuditService::class)->historyFor(
            $contract,
            $validated,
            (int) ($validated['offset'] ?? 0),
            (int) ($validated['limit'] ?? 50),
        );

        return response()->json(
            (new \App\Http\Transformers\ContractAuditTransformer)->transform($page['rows'], $page['total']),
            200,
            ['Content-Type' => 'application/json;charset=utf8'],
            JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Return the assets that can be linked to this contract.
     *
     * This is intentionally contract-scoped instead of changing the shared
     * hardware selectlist contract used by other screens.
     */
    public function assetSelectlist(Request $request, Contract $contract): array
    {
        $this->authorize('update', $contract);
        $this->authorize('view', Asset::class);

        $assets = Asset::query()
            ->select([
                'assets.id',
                'assets.name',
                'assets.asset_tag',
                'assets.serial',
                'assets.model_id',
                'assets.assigned_to',
                'assets.assigned_type',
                'assets.status_id',
                'assets.company_id',
            ])
            ->with('model', 'assetstatus', 'assignedTo')
            ->NotArchived();

        if ($contract->company_id === null) {
            $assets->whereNull('assets.company_id');
        } else {
            $assets->where('assets.company_id', $contract->company_id);
        }

        $assets->whereNotExists(function (Builder $query) use ($contract): void {
            $query->select(DB::raw('1'))
                ->from('contract_asset')
                ->whereColumn('contract_asset.asset_id', 'assets.id')
                ->where('contract_asset.contract_id', $contract->getKey());
        });

        if ($request->filled('assetStatusType') && $request->input('assetStatusType') === 'RTD') {
            $assets->RTD();
        }

        if ($request->filled('search')) {
            $assets->AssignedSearch($request->input('search'));
        }

        $assets = $assets->orderBy('assets.asset_tag')->paginate(50);

        foreach ($assets as $asset) {
            $asset->use_text = $asset->present()->fullName;

            if ($asset->checkedOutToUser() && $asset->assigned) {
                $asset->use_text .= ' → '.$asset->assigned->display_name;
            }

            if ($asset->assetstatus?->getStatuslabelType() === 'pending') {
                $asset->use_text .= ' (pending)';
            }

            $asset->use_image = $asset->getImageUrl() ?: null;
        }

        return (new SelectlistTransformer)->transformSelectlist($assets);
    }

    /**
     * Attach an asset to the contract.
     */
    public function attachAsset(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $this->authorize('view', Asset::class);

        $validator = Validator::make($request->all(), [
            'asset_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->assetErrorRedirect($contract, trans('admin/contracts/message.asset.not_available'))
                ->withErrors($validator)
                ->withInput();
        }

        $asset = Asset::query()->whereKey((int) $request->input('asset_id'))->first();
        if (! $asset) {
            return $this->assetErrorRedirect($contract, trans('admin/contracts/message.asset.not_available'));
        }

        $this->authorize('view', $asset);

        try {
            $result = $this->assetLinkService->attach($contract, $asset->id);
        } catch (ContractAssetLinkException $exception) {
            return $this->assetErrorRedirect($contract, $this->assetLinkErrorMessage($exception));
        }

        if ($result === ContractAssetLinkService::ALREADY_LINKED) {
            return $this->assetErrorRedirect($contract, trans('admin/contracts/message.asset.already_linked'));
        }

        return $this->assetRedirect($contract)
            ->with('success', trans('admin/contracts/message.asset.attach.success'));
    }

    /**
     * Detach an asset from the contract.
     */
    public function detachAsset(Contract $contract, $assetId): RedirectResponse
    {
        $this->authorize('update', $contract);
        $this->authorize('view', Asset::class);

        $validator = Validator::make(['asset_id' => $assetId], [
            'asset_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->assetErrorRedirect($contract, trans('admin/contracts/message.asset.not_available'))
                ->withErrors($validator);
        }

        $asset = Asset::query()->whereKey((int) $assetId)->first();
        if (! $asset) {
            return $this->assetErrorRedirect($contract, trans('admin/contracts/message.asset.not_available'));
        }

        $this->authorize('view', $asset);

        try {
            $result = $this->assetLinkService->detach($contract, $asset->id);
        } catch (ContractAssetLinkException $exception) {
            return $this->assetErrorRedirect($contract, $this->assetLinkErrorMessage($exception));
        }

        if ($result === ContractAssetLinkService::NOT_LINKED) {
            return $this->assetErrorRedirect($contract, trans('admin/contracts/message.asset.not_linked'));
        }

        return $this->assetRedirect($contract)
            ->with('success', trans('admin/contracts/message.asset.detach.success'));
    }

    private function assetRedirect(Contract $contract): RedirectResponse
    {
        return redirect()->route('contracts.show', $contract->getKey())
            ->withFragment('contract-assets');
    }

    private function assetErrorRedirect(Contract $contract, string $message): RedirectResponse
    {
        return $this->assetRedirect($contract)->with('error', $message);
    }

    private function assetLinkErrorMessage(ContractAssetLinkException $exception): string
    {
        return match ($exception->reason) {
            ContractAssetLinkException::CONTRACT_CLOSED => trans('admin/contracts/message.asset.contract_closed'),
            ContractAssetLinkException::COMPANY_MISMATCH,
            ContractAssetLinkException::ASSET_NOT_AVAILABLE => trans('admin/contracts/message.asset.not_available'),
            default => trans('admin/contracts/message.asset.attach.error'),
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Exceptions\ContractAssetLinkException;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Company;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Services\ContractAssetLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use App\Services\Contracts\ContractAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        return view('contracts/edit')->with('item', new Contract);
    }

    /**
     * Contract create form processing.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $contract = new Contract;
        $contract->name = $request->input('name');
        $contract->contract_number = $request->input('contract_number');
        $contract->contract_type = $request->input('contract_type');
        $contract->supplier_id = $request->input('supplier_id');
        $contract->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $contract->start_date = $request->input('start_date');
        $contract->end_date = $request->input('end_date');
        $contract->billing_cycle = $request->input('billing_cycle');
        $contract->billing_day = $request->input('billing_day');
        $contract->installment_value = $request->input('installment_value');
        $contract->total_value = $request->input('total_value');
        $contract->total_installments = $request->input('total_installments');
        $contract->readjustment_index = $request->input('readjustment_index');
        $contract->readjustment_month = $request->input('readjustment_month');
        $contract->description = $request->input('description');
        $contract->notes = $request->input('notes');
        $contract->created_by = auth()->id();

        // Set default status (draft)
        $defaultStatus = ContractStatusLabel::defaultForMetaType('contract', 'draft');
        $contract->status_label_id = $request->input('status_label_id', $defaultStatus?->id);

        return $contract->getConnection()->transaction(function () use ($request, $contract) {
            if ($contract->save()) {
                app(ContractAuditService::class)->record(
                    $contract,
                    'contract.created',
                    $contract,
                    [],
                    app(ContractAuditService::class)->snapshot($contract),
                );

                // Generate installments only if checkbox is checked (default: checked)
                if ($request->has('auto_generate_installments') && $contract->start_date) {
                    if ($contract->contract_type === 'recurring'
                        || ($contract->contract_type === 'one_time'
                            && ($contract->total_value > 0 || $contract->installment_value > 0))) {
                        $contract->generateInstallments();
                    }
                }

                return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.create.success'));
            }

            return redirect()->back()->withInput()->withErrors($contract->getErrors());
        });
    }

    /**
     * Contract update.
     */
    public function edit(Contract $contract): View|RedirectResponse
    {
        $this->authorize('update', $contract);

        return view('contracts/edit')->with('item', $contract);
    }

    /**
     * Contract update form processing page.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);
        $before = app(ContractAuditService::class)->snapshot($contract);

        $contract->name = $request->input('name');
        $contract->contract_number = $request->input('contract_number');
        $contract->contract_type = $request->input('contract_type');
        $contract->supplier_id = $request->input('supplier_id');
        $contract->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $contract->start_date = $request->input('start_date');
        $contract->end_date = $request->input('end_date');
        $contract->billing_cycle = $request->input('billing_cycle');
        $contract->billing_day = $request->input('billing_day');
        $contract->installment_value = $request->input('installment_value');
        $contract->total_value = $request->input('total_value');
        $contract->total_installments = $request->input('total_installments');
        $contract->readjustment_index = $request->input('readjustment_index');
        $contract->readjustment_month = $request->input('readjustment_month');
        $contract->description = $request->input('description');
        $contract->notes = $request->input('notes');
        $contract->status_label_id = $request->input('status_label_id');

        return $contract->getConnection()->transaction(function () use ($contract, $before) {
            $changed = $contract->isDirty();
            if ($contract->save()) {
                if ($changed) {
                    app(ContractAuditService::class)->record(
                        $contract,
                        'contract.updated',
                        $contract,
                        $before,
                        app(ContractAuditService::class)->snapshot($contract),
                    );
                }

                return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.update.success'));
            }

            return redirect()->back()->withInput()->withErrors($contract->getErrors());
        });
    }

    /**
     * Delete the given contract.
     */
    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        return $contract->getConnection()->transaction(function () use ($contract) {
            $contract = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('delete', $contract);

            if (! $contract->isDeletable()) {
                return redirect()->route('contracts.index')->with('error', trans('admin/contracts/message.assoc_installments'));
            }

            $before = app(ContractAuditService::class)->snapshot($contract);
            $contract->delete();
            app(ContractAuditService::class)->record(
                $contract,
                'contract.deleted',
                $contract,
                $before,
                app(ContractAuditService::class)->snapshot($contract),
            );

            return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.delete.success'));
        });
    }

    /**
     * Get the contract information to present to the contract view page.
     */
    public function show(Contract $contract): View|RedirectResponse
    {
        $this->authorize('view', $contract);

        $contract->load(['installments.statusLabel', 'installments.adminuser', 'installments.uploads.adminuser', 'amendments.adminuser', 'assets.model', 'assets.assetstatus']);
        $auditHistoryCount = app(ContractAuditService::class)->historyFor($contract, [], 0, 1)['total'];

        return view('contracts/view', compact('contract', 'auditHistoryCount'));
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

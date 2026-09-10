<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractType;
use App\Services\ContractInput;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractsController extends Controller
{
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
            $data = ContractInput::validate($request->all(), false, $contract);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        ContractInput::apply($contract, $data);

        try {
            if (! $contract->save()) {
                return redirect()->back()->withInput()->withErrors($contract->getErrors());
            }
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->back()->withInput()->withErrors([
                'contract' => trans('admin/contracts/message.update.error'),
            ]);
        }

        return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.update.success'));
    }

    /**
     * Delete the given contract.
     */
    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);

        if (! $contract->isDeletable()) {
            return redirect()->route('contracts.index')->with('error', trans('admin/contracts/message.assoc_installments'));
        }

        $contract->delete();

        return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.delete.success'));
    }

    /**
     * Get the contract information to present to the contract view page.
     */
    public function show(Contract $contract): View|RedirectResponse
    {
        $this->authorize('view', $contract);

        $contract->load(['contractType', 'installments.statusLabel', 'installments.adminuser', 'installments.uploads.adminuser', 'amendments.adminuser', 'assets.model', 'assets.assetstatus']);
        $contractPreview = null;
        try {
            $contractPreview = $contract->installmentPreview();
        } catch (\InvalidArgumentException) {
            // Preserve display of legacy records with combinations that are
            // no longer valid for new contracts.
        }

        return view('contracts/view', compact('contract', 'contractPreview'));
    }

    /**
     * Attach an asset to the contract.
     */
    public function attachAsset(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $request->validate([
            'asset_id' => 'required|exists:assets,id',
        ]);

        $asset = Asset::findOrFail($request->input('asset_id'));
        $this->authorize('view', $asset);
        $assetId = $asset->id;

        // Prevent duplicate attachment
        if ($contract->assets()->where('assets.id', $assetId)->exists()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.asset.already_linked'))
                ->withFragment('contract-assets');
        }

        $contract->assets()->attach($assetId, ['created_at' => now()]);

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', trans('admin/contracts/message.asset.attach.success'))
            ->withFragment('contract-assets');
    }

    /**
     * Detach an asset from the contract.
     */
    public function detachAsset(Contract $contract, $assetId): RedirectResponse
    {
        $this->authorize('update', $contract);

        $contract->assets()->detach($assetId);

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', trans('admin/contracts/message.asset.detach.success'))
            ->withFragment('contract-assets');
    }
}

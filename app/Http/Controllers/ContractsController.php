<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        if ($contract->save()) {
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

        if ($contract->save()) {
            return redirect()->route('contracts.index')->with('success', trans('admin/contracts/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($contract->getErrors());
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

        $contract->load(['installments.statusLabel', 'installments.adminuser', 'amendments.adminuser', 'assets.model', 'assets.assetstatus']);

        return view('contracts/view', compact('contract'));
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

        $assetId = $request->input('asset_id');

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

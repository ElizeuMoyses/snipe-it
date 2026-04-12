<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractsController extends Controller
{
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
            // Generate installments if contract is recurring and has the necessary data
            if ($contract->contract_type === 'recurring' && $contract->start_date && $contract->end_date) {
                $contract->generateInstallments();
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
        $this->authorize('update', Contract::class);

        return view('contracts/edit')->with('item', $contract);
    }

    /**
     * Contract update form processing page.
     */
    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', Contract::class);

        $contract->name = $request->input('name');
        $contract->contract_number = $request->input('contract_number');
        $contract->contract_type = $request->input('contract_type');
        $contract->supplier_id = $request->input('supplier_id');
        $contract->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $contract->start_date = $request->input('start_date');
        $contract->end_date = $request->input('end_date');
        $contract->billing_cycle = $request->input('billing_cycle');
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
        $this->authorize('delete', Contract::class);

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
        $this->authorize('view', Contract::class);

        return view('contracts/view', compact('contract'));
    }
}

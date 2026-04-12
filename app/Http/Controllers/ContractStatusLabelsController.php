<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractStatusLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractStatusLabelsController extends Controller
{
    /**
     * Show a list of all contract status labels.
     */
    public function index(): View
    {
        $this->authorize('view', Contract::class);

        return view('contract-status-labels/index');
    }

    /**
     * Contract status label create.
     */
    public function create(): View
    {
        $this->authorize('create', Contract::class);

        return view('contract-status-labels/edit')
            ->with('item', new ContractStatusLabel)
            ->with('scopes', ['contract' => trans('admin/contract_status_labels/general.scope_contract'), 'installment' => trans('admin/contract_status_labels/general.scope_installment')])
            ->with('meta_types', ContractStatusLabel::META_TYPES);
    }

    /**
     * Contract status label create form processing.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $statusLabel = new ContractStatusLabel;
        $statusLabel->name = $request->input('name');
        $statusLabel->scope = $request->input('scope');
        $statusLabel->meta_type = $request->input('meta_type');
        $statusLabel->color = $request->input('color');
        $statusLabel->icon = $request->input('icon');
        $statusLabel->sort_order = $request->input('sort_order', 0);
        $statusLabel->is_default = $request->input('is_default', false);
        $statusLabel->notes = $request->input('notes');
        $statusLabel->created_by = auth()->id();

        // If this label is set as default, clear other defaults for same scope+meta_type
        if ($statusLabel->is_default) {
            ContractStatusLabel::where('scope', $statusLabel->scope)
                ->where('meta_type', $statusLabel->meta_type)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        if ($statusLabel->save()) {
            return redirect()->route('contract-status-labels.index')->with('success', trans('admin/contract_status_labels/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($statusLabel->getErrors());
    }

    /**
     * Contract status label update.
     */
    public function edit(ContractStatusLabel $contract_status_label): View|RedirectResponse
    {
        $this->authorize('update', Contract::class);

        return view('contract-status-labels/edit')
            ->with('item', $contract_status_label)
            ->with('scopes', ['contract' => trans('admin/contract_status_labels/general.scope_contract'), 'installment' => trans('admin/contract_status_labels/general.scope_installment')])
            ->with('meta_types', ContractStatusLabel::META_TYPES);
    }

    /**
     * Contract status label update form processing page.
     */
    public function update(Request $request, ContractStatusLabel $contract_status_label): RedirectResponse
    {
        $this->authorize('update', Contract::class);

        $contract_status_label->name = $request->input('name');
        $contract_status_label->scope = $request->input('scope');
        $contract_status_label->meta_type = $request->input('meta_type');
        $contract_status_label->color = $request->input('color');
        $contract_status_label->icon = $request->input('icon');
        $contract_status_label->sort_order = $request->input('sort_order', 0);
        $contract_status_label->is_default = $request->input('is_default', false);
        $contract_status_label->notes = $request->input('notes');

        // If this label is set as default, clear other defaults for same scope+meta_type
        if ($contract_status_label->is_default) {
            ContractStatusLabel::where('scope', $contract_status_label->scope)
                ->where('meta_type', $contract_status_label->meta_type)
                ->where('is_default', true)
                ->where('id', '!=', $contract_status_label->id)
                ->update(['is_default' => false]);
        }

        if ($contract_status_label->save()) {
            return redirect()->route('contract-status-labels.index')->with('success', trans('admin/contract_status_labels/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($contract_status_label->getErrors());
    }

    /**
     * Delete the given contract status label.
     */
    public function destroy($id): RedirectResponse
    {
        $this->authorize('delete', Contract::class);

        $statusLabel = ContractStatusLabel::find($id);

        if (is_null($statusLabel)) {
            return redirect()->route('contract-status-labels.index')->with('error', trans('admin/contract_status_labels/message.not_found'));
        }

        if (! $statusLabel->isDeletable()) {
            return redirect()->route('contract-status-labels.index')->with('error', trans('admin/contract_status_labels/message.assoc_contracts'));
        }

        $statusLabel->delete();

        return redirect()->route('contract-status-labels.index')->with('success', trans('admin/contract_status_labels/message.delete.success'));
    }

    /**
     * Get the contract status label information to present to the view page.
     */
    public function show(ContractStatusLabel $contract_status_label): View|RedirectResponse
    {
        $this->authorize('view', Contract::class);

        return view('contract-status-labels/view')->with('statuslabel', $contract_status_label);
    }
}

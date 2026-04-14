<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractInstallmentsController extends Controller
{
    /**
     * Show form for creating a new installment.
     */
    public function create(Contract $contract): View|RedirectResponse
    {
        $this->authorize('installments', $contract);

        // Guard: cannot create installments on terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.contract_terminal'));
        }

        return view('contracts/installments/edit', [
            'contract' => $contract,
            'item'     => new ContractInstallment,
        ]);
    }

    /**
     * Store a newly created installment.
     */
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('installments', $contract);

        // Guard: cannot create installments on terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.contract_terminal'));
        }

        $installment = new ContractInstallment;
        $installment->contract_id = $contract->id;
        $installment->installment_number = $request->input('installment_number');
        $installment->reference_date = $request->input('reference_date');
        $installment->due_date = $request->input('due_date');
        $installment->expected_value = $request->input('expected_value');
        $installment->notes = $request->input('notes');
        $installment->created_by = auth()->id();

        // Force default pending status — never accept from request
        $defaultPending = ContractStatusLabel::defaultForMetaType('installment', 'pending');
        if (! $defaultPending) {
            return redirect()->back()->withInput()
                ->with('error', trans('admin/contracts/message.installment.create.missing_default_status'));
        }
        $installment->status_label_id = $defaultPending->id;

        if ($installment->save()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.installment.create.success'))
                ->withFragment('installments');
        }

        return redirect()->back()->withInput()->withErrors($installment->getErrors());
    }

    /**
     * Generate all installments for a contract in batch.
     */
    public function generate(Contract $contract): RedirectResponse
    {
        $this->authorize('installments', $contract);

        // Guard: terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.contract_terminal'));
        }

        // Guard: only generate if no installments exist
        if ($contract->installments()->count() > 0) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('warning', trans('admin/contracts/message.installment.already_generated'))
                ->withFragment('installments');
        }

        $count = $contract->generateInstallments();

        if ($count > 0) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.installment.generate.success', ['count' => $count]))
                ->withFragment('installments');
        }

        return redirect()->route('contracts.show', $contract->id)
            ->with('error', trans('admin/contracts/message.installment.generate.error'))
            ->withFragment('installments');
    }

    /**
     * Show form for editing an installment.
     */
    public function edit(Contract $contract, $installmentId): View|RedirectResponse
    {
        $installment = $contract->installments()->findOrFail($installmentId);
        $this->authorize('installments', $contract);

        // Guard: terminal installments are not editable
        if ($installment->statusLabel?->isTerminal()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.terminal_locked'));
        }

        return view('contracts/installments/edit', [
            'contract' => $contract,
            'item'     => $installment,
        ]);
    }

    /**
     * Update the specified installment.
     */
    public function update(Request $request, Contract $contract, $installmentId): RedirectResponse
    {
        $installment = $contract->installments()->findOrFail($installmentId);
        $this->authorize('installments', $contract);

        // Guard: terminal installments are not editable
        if ($installment->statusLabel?->isTerminal()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.terminal_locked'));
        }

        // Only allow editing basic fields — payment fields are exclusive to payment flow
        $installment->installment_number = $request->input('installment_number');
        $installment->reference_date = $request->input('reference_date');
        $installment->due_date = $request->input('due_date');
        $installment->expected_value = $request->input('expected_value');
        $installment->notes = $request->input('notes');

        if ($installment->save()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.installment.update.success'))
                ->withFragment('installments');
        }

        return redirect()->back()->withInput()->withErrors($installment->getErrors());
    }

    /**
     * Delete the specified installment.
     */
    public function destroy(Contract $contract, $installmentId): RedirectResponse
    {
        $installment = $contract->installments()->findOrFail($installmentId);
        $this->authorize('installments', $contract);

        // Guard: terminal installments cannot be deleted
        if ($installment->statusLabel?->isTerminal()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.terminal_locked'));
        }

        $installment->delete();

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', trans('admin/contracts/message.installment.delete.success'))
            ->withFragment('installments');
    }

    /**
     * Show payment registration form (GET).
     */
    public function registerPayment(Contract $contract, $installmentId): View|RedirectResponse
    {
        $installment = $contract->installments()->findOrFail($installmentId);
        $this->authorize('installments', $contract);

        // Guard: only pending and overdue can receive payment
        if ($installment->statusLabel?->isTerminal()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.payment.already_terminal'));
        }

        $allowedMeta = ['pending', 'overdue'];
        if (! in_array($installment->statusLabel?->meta_type, $allowedMeta)) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.payment.already_terminal'));
        }

        return view('contracts/installments/pay', [
            'contract'    => $contract,
            'installment' => $installment,
        ]);
    }

    /**
     * Process payment registration (POST).
     */
    public function storePayment(Request $request, Contract $contract, $installmentId): RedirectResponse
    {
        $installment = $contract->installments()->findOrFail($installmentId);
        $this->authorize('installments', $contract);

        // Guard: only pending and overdue can receive payment
        if ($installment->statusLabel?->isTerminal()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.payment.already_terminal'));
        }

        $allowedMeta = ['pending', 'overdue'];
        if (! in_array($installment->statusLabel?->meta_type, $allowedMeta)) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.installment.payment.already_terminal'));
        }

        $request->validate([
            'paid_value'       => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'payment_method'   => 'nullable|string|max:100',
            'ticket_reference' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        $defaultPaid = ContractStatusLabel::defaultForMetaType('installment', 'paid');
        if (! $defaultPaid) {
            return redirect()->back()->withInput()
                ->with('error', trans('admin/contracts/message.installment.payment.missing_default_status'));
        }
        $installment->paid_value = $request->input('paid_value');
        $installment->payment_date = $request->input('payment_date');
        $installment->payment_method = $request->input('payment_method');
        $installment->ticket_reference = $request->input('ticket_reference');
        if ($request->filled('notes')) {
            $installment->notes = $request->input('notes');
        }
        $installment->status_label_id = $defaultPaid->id;

        if ($installment->save()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.installment.payment.success'))
                ->withFragment('installments');
        }

        return redirect()->back()->withInput()->withErrors($installment->getErrors());
    }

    /**
     * Update installment sub-status (PATCH).
     */
    public function updateStatus(Request $request, Contract $contract, $installmentId): RedirectResponse
    {
        $installment = $contract->installments()->findOrFail($installmentId);
        $this->authorize('installments', $contract);

        $request->validate([
            'status_label_id' => 'required|exists:contract_status_labels,id',
        ]);

        $newStatusLabel = ContractStatusLabel::findOrFail($request->input('status_label_id'));
        $currentStatusLabel = $installment->statusLabel;

        // 1. Scope check: must be installment scope
        if ($newStatusLabel->scope !== 'installment') {
            return redirect()->back()
                ->with('error', trans('admin/contracts/message.installment.status.invalid_scope'));
        }

        // 2. Terminal check: cannot change from terminal status
        if ($currentStatusLabel?->isTerminal()) {
            return redirect()->back()
                ->with('error', trans('admin/contracts/message.installment.status.terminal_locked'));
        }

        // 3. Same meta_type: allow unconditionally (free transition)
        $currentMeta = $currentStatusLabel?->meta_type;
        $newMeta = $newStatusLabel->meta_type;

        if ($currentMeta !== $newMeta) {
            // 4. Cross meta_type: validate against allowed transitions
            // Note: paid is NOT reachable via updateStatus — only via storePayment
            $allowedTransitions = [
                'pending' => ['cancelled'],
                'overdue' => [],
            ];

            if (! in_array($newMeta, $allowedTransitions[$currentMeta] ?? [])) {
                return redirect()->back()
                    ->with('error', trans('admin/contracts/message.installment.status.transition_blocked'));
            }
        }

        $installment->status_label_id = $newStatusLabel->id;
        $installment->save();

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', trans('admin/contracts/message.installment.status.success'))
            ->withFragment('installments');
    }
}

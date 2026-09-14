<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use App\Services\Contracts\ContractAuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractInstallmentsController extends Controller
{
    public function bulkDestroy(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('installments', $contract);
        $data = $request->validate([
            'installment_ids' => 'required|array|min:1|max:500',
            'installment_ids.*' => 'required|integer|min:1|distinct',
        ]);
        return $contract->getConnection()->transaction(function () use ($data, $contract) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $this->authorize('installments', $contract);
            $items = $contract->installments()->whereIn('id', $data['installment_ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($items->count() === count($data['installment_ids']), 404);
            foreach ($items as $item) {
                if ($item->statusLabel?->isTerminal() || $item->paid_value !== null || $item->payment_date !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'installment_ids' => trans('admin/contracts/installment_ux.bulk_locked'),
                    ]);
                }
            }
            $audit = app(ContractAuditService::class);
            foreach ($items as $item) {
                $before = $audit->snapshot($item);
                if (! $item->delete()) {
                    throw new \RuntimeException('Installment deletion failed.');
                }
                $audit->record($contract, 'installment.deleted', $item, $before, $audit->snapshot($item), ['operation' => 'bulk_delete']);
            }
            return redirect()->route('contracts.show', $contract->id)->withFragment('installments')
                ->with('success', trans('admin/contracts/installment_ux.bulk_success', ['count' => $items->count()]));
        });
    }

    public function showReopen(Contract $contract, $installmentId): View|RedirectResponse
    {
        $this->authorize('installments', $contract);
        $item = $contract->installments()->findOrFail($installmentId);
        if ($item->statusLabel?->meta_type !== 'paid' || in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return redirect()->route('contracts.show', $contract->id)->with('error', trans('admin/contracts/installment_ux.reopen_locked'));
        }
        return view('contracts.installments.reopen', ['contract' => $contract, 'installment' => $item]);
    }

    public function reopen(Request $request, Contract $contract, $installmentId): RedirectResponse
    {
        $this->authorize('installments', $contract);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        return $contract->getConnection()->transaction(function () use ($data, $contract, $installmentId) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $this->authorize('installments', $contract);
            $item = $contract->installments()->lockForUpdate()->findOrFail($installmentId);
            if ($item->statusLabel?->meta_type !== 'paid' || in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
                return redirect()->back()->with('error', trans('admin/contracts/installment_ux.reopen_locked'));
            }
            $pending = ContractStatusLabel::defaultForMetaType('installment', 'pending');
            if (! $pending) {
                return redirect()->back()->with('error', trans('admin/contracts/message.installment.create.missing_default_status'));
            }
            $audit = app(ContractAuditService::class);
            $before = $audit->snapshot($item);
            $item->paid_value = null;
            $item->payment_date = null;
            $item->payment_method = null;
            $item->status_label_id = $pending->id;
            if (! $item->save()) {
                throw \Illuminate\Validation\ValidationException::withMessages($item->getErrors()->toArray());
            }
            $audit->record($contract, 'installment.status_changed', $item, $before, $audit->snapshot($item), [
                'operation' => 'payment_reopened', 'reason' => $data['reason'],
                'status_before' => 'paid', 'status_after' => 'pending',
            ]);
            return redirect()->route('contracts.show', $contract->id)->withFragment('installments')
                ->with('success', trans('admin/contracts/installment_ux.reopen_success'));
        });
    }

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
        return $contract->getConnection()->transaction(function () use ($request, $contract) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
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
                app(ContractAuditService::class)->record(
                    $contract,
                    'installment.created',
                    $installment,
                    [],
                    app(ContractAuditService::class)->snapshot($installment),
                );

                return redirect()->route('contracts.show', $contract->id)
                    ->with('success', trans('admin/contracts/message.installment.create.success'))
                    ->withFragment('installments');
            }

            return redirect()->back()->withInput()->withErrors($installment->getErrors());
        });
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
        return $contract->getConnection()->transaction(function () use ($request, $contract, $installmentId) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $installment = $contract->installments()->lockForUpdate()->findOrFail($installmentId);
            $this->authorize('installments', $contract);

            // Guard: terminal installments are not editable
            if ($installment->statusLabel?->isTerminal()) {
                return redirect()->route('contracts.show', $contract->id)
                    ->with('error', trans('admin/contracts/message.installment.terminal_locked'));
            }

            $before = app(ContractAuditService::class)->snapshot($installment);

            // Only allow editing basic fields — payment fields are exclusive to payment flow
            $installment->installment_number = $request->input('installment_number');
            $installment->reference_date = $request->input('reference_date');
            $installment->due_date = $request->input('due_date');
            $installment->expected_value = $request->input('expected_value');
            $installment->notes = $request->input('notes');

            $changed = $installment->isDirty();
            if ($installment->save()) {
                if ($changed) {
                    app(ContractAuditService::class)->record(
                        $contract,
                        'installment.updated',
                        $installment,
                        $before,
                        app(ContractAuditService::class)->snapshot($installment),
                    );
                }

                return redirect()->route('contracts.show', $contract->id)
                    ->with('success', trans('admin/contracts/message.installment.update.success'))
                    ->withFragment('installments');
            }

            return redirect()->back()->withInput()->withErrors($installment->getErrors());
        });
    }

    /**
     * Delete the specified installment.
     */
    public function destroy(Contract $contract, $installmentId): RedirectResponse
    {
        return $contract->getConnection()->transaction(function () use ($contract, $installmentId) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $installment = $contract->installments()->lockForUpdate()->findOrFail($installmentId);
            $this->authorize('installments', $contract);

            // Guard: terminal installments cannot be deleted
            if ($installment->statusLabel?->isTerminal()) {
                return redirect()->route('contracts.show', $contract->id)
                    ->with('error', trans('admin/contracts/message.installment.terminal_locked'));
            }

            $before = app(ContractAuditService::class)->snapshot($installment);
            $installment->delete();
            app(ContractAuditService::class)->record(
                $contract,
                'installment.deleted',
                $installment,
                $before,
                app(ContractAuditService::class)->snapshot($installment),
            );

            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.installment.delete.success'))
                ->withFragment('installments');
        });
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
        return $contract->getConnection()->transaction(function () use ($request, $contract, $installmentId) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $installment = $contract->installments()->lockForUpdate()->findOrFail($installmentId);
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

            // Browser dates are localized; keep canonical ISO requests compatible.
            $paymentDate = $request->input('payment_date');
            if (is_string($paymentDate) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $paymentDate)) {
                $parsed = \DateTimeImmutable::createFromFormat('!d/m/Y', $paymentDate);
                if ($parsed && $parsed->format('d/m/Y') === $paymentDate) {
                    $request->merge(['payment_date' => $parsed->format('Y-m-d')]);
                }
            }

            $request->validate([
                'paid_value'       => 'required|numeric|min:0.01',
                'payment_date'     => 'required|date_format:Y-m-d',
                'payment_method'   => 'nullable|string|max:100',
                'ticket_reference' => 'nullable|string|max:100',
                'notes'            => 'nullable|string',
            ]);

            $defaultPaid = ContractStatusLabel::defaultForMetaType('installment', 'paid');
            if (! $defaultPaid) {
                return redirect()->back()->withInput()
                    ->with('error', trans('admin/contracts/message.installment.payment.missing_default_status'));
            }
            $before = app(ContractAuditService::class)->snapshot($installment);
            $installment->paid_value = $request->input('paid_value');
            $installment->payment_date = $request->input('payment_date');
            $installment->payment_method = $request->input('payment_method');
            $installment->ticket_reference = $request->input('ticket_reference');
            if ($request->filled('notes')) {
                $installment->notes = $request->input('notes');
            }
            $installment->status_label_id = $defaultPaid->id;

            if ($installment->save()) {
                app(ContractAuditService::class)->record(
                    $contract,
                    'installment.paid',
                    $installment,
                    $before,
                    app(ContractAuditService::class)->snapshot($installment),
                );

                return redirect()->route('contracts.show', $contract->id)
                    ->with('success', trans('admin/contracts/message.installment.payment.success'))
                    ->withFragment('installments');
            }

            return redirect()->back()->withInput()->withErrors($installment->getErrors());
        });
    }

    /**
     * Update installment sub-status (PATCH).
     */
    public function updateStatus(Request $request, Contract $contract, $installmentId): RedirectResponse
    {
        return $contract->getConnection()->transaction(function () use ($request, $contract, $installmentId) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $installment = $contract->installments()->lockForUpdate()->findOrFail($installmentId);
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

            $before = app(ContractAuditService::class)->snapshot($installment);
            $installment->status_label_id = $newStatusLabel->id;
            if (! $installment->save()) {
                return redirect()->back()->withInput()->withErrors($installment->getErrors());
            }
            app(ContractAuditService::class)->record(
                $contract,
                'installment.status_changed',
                $installment,
                $before,
                app(ContractAuditService::class)->snapshot($installment),
                [
                    'status_before' => $currentStatusLabel?->name,
                    'status_after' => $newStatusLabel->name,
                ],
            );

            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.installment.status.success'))
                ->withFragment('installments');
        });
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Models\Actionlog;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractStatusLabel;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractAmendmentsController extends Controller
{
    /**
     * Show form for creating a new amendment.
     */
    public function create(Contract $contract): View|RedirectResponse
    {
        $this->authorize('update', $contract);

        // Guard: cannot create amendments on terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.amendment.contract_terminal'));
        }

        return view('contracts/amendments/edit', [
            'contract' => $contract,
            'item'     => new ContractAmendment,
        ]);
    }

    /**
     * Preview amendment impact without executing side-effects (AJAX).
     */
    public function preview(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        $amendmentType = $request->input('amendment_type');

        $preview = match ($amendmentType) {
            'readjustment' => $this->previewReadjustment($request, $contract),
            'renewal'      => $this->previewRenewal($request, $contract),
            'termination'  => $this->previewTermination($request, $contract),
            'scope_change' => ['type' => 'scope_change', 'has_side_effects' => false],
            default        => ['type' => 'unknown', 'has_side_effects' => false],
        };

        return response()->json($preview);
    }

    private function previewReadjustment(Request $request, Contract $contract): array
    {
        $effectiveDate = $request->input('effective_date');
        $newValue = $request->input('new_value');

        $pendingCount = $contract->installments()
            ->pending()
            ->where('due_date', '>=', $effectiveDate)
            ->count();

        $overdueCount = $contract->installments()->overdue()->count();

        return [
            'type'              => 'readjustment',
            'has_side_effects'  => true,
            'pending_affected'  => $pendingCount,
            'overdue_unchanged' => $overdueCount,
            'old_value'         => $contract->installment_value,
            'new_value'         => $newValue,
            'message'           => trans('admin/contracts/message.amendment.readjustment.preview', [
                'count' => $pendingCount,
                'old'   => $contract->installment_value,
                'new'   => $newValue,
            ]),
        ];
    }

    private function previewRenewal(Request $request, Contract $contract): array
    {
        $oldEnd = Carbon::parse($request->input('old_end_date'));
        $newEnd = Carbon::parse($request->input('new_end_date'));

        $monthsInterval = match ($contract->billing_cycle) {
            'monthly'    => 1,
            'quarterly'  => 3,
            'semiannual' => 6,
            'annual'     => 12,
            default      => 1,
        };

        $estimatedCount = 0;
        $cursor = $oldEnd->copy()->addDay();
        while ($cursor->lte($newEnd)) {
            $estimatedCount++;
            $cursor->addMonths($monthsInterval);
        }

        return [
            'type'              => 'renewal',
            'has_side_effects'  => true,
            'estimated_count'   => $estimatedCount,
            'new_end_date'      => $newEnd->format('Y-m-d'),
            'message'           => trans('admin/contracts/message.amendment.renewal.preview', [
                'count' => $estimatedCount,
                'date'  => $newEnd->format('d/m/Y'),
            ]),
        ];
    }

    private function previewTermination(Request $request, Contract $contract): array
    {
        $effectiveDate = $request->input('effective_date');

        $pendingCancelCount = $contract->installments()
            ->pending()
            ->where('due_date', '>', $effectiveDate)
            ->count();

        $overdueCount = $contract->installments()->overdue()->count();
        $paidCount = $contract->installments()->paid()->count();

        return [
            'type'              => 'termination',
            'has_side_effects'  => true,
            'pending_cancel'    => $pendingCancelCount,
            'overdue_unchanged' => $overdueCount,
            'paid_unchanged'    => $paidCount,
            'message'           => trans('admin/contracts/message.amendment.termination.preview', [
                'count' => $pendingCancelCount,
                'date'  => $request->input('effective_date'),
            ]),
        ];
    }

    /**
     * Store a newly created amendment with side-effects.
     */
    public function store(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        // Guard: cannot create amendments on terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.amendment.contract_terminal'));
        }

        $amendmentType = $request->input('amendment_type');

        // Type-specific validation rules
        $extraRules = match ($amendmentType) {
            'readjustment' => [
                'old_value' => 'required|numeric|min:0',
                'new_value' => 'required|numeric|min:0.01',
            ],
            'renewal' => [
                'old_end_date' => 'required|date',
                'new_end_date' => 'required|date|after:old_end_date',
            ],
            default => [],
        };

        $request->validate(array_merge([
            'amendment_type'   => 'required|in:readjustment,scope_change,renewal,termination',
            'description'      => 'required|string',
            'effective_date'   => 'required|date',
            'ticket_reference' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ], $extraRules));

        // Guard: readjustment requires active contract
        if ($amendmentType === 'readjustment' && $contract->statusLabel?->meta_type !== 'active') {
            return redirect()->back()->withInput()
                ->with('error', trans('admin/contracts/message.amendment.contract_not_active'));
        }

        // Guard: renewal not allowed on draft contracts
        if ($amendmentType === 'renewal' && $contract->statusLabel?->meta_type === 'draft') {
            return redirect()->back()->withInput()
                ->with('error', trans('admin/contracts/message.amendment.not_activatable'));
        }

        // Guard: renewal overlap
        if ($amendmentType === 'renewal') {
            if ($request->date('new_end_date') <= $request->date('old_end_date')) {
                return redirect()->back()->withInput()
                    ->with('error', trans('admin/contracts/message.amendment.renewal.overlap'));
            }
        }

        $amendment = null;
        $sideEffectResult = [];

        DB::transaction(function () use ($request, $contract, $amendmentType, &$amendment, &$sideEffectResult) {
            $amendment = new ContractAmendment;
            $amendment->contract_id = $contract->id;
            $amendment->amendment_type = $amendmentType;
            $amendment->description = $request->input('description');
            $amendment->old_value = $request->input('old_value');
            $amendment->new_value = $request->input('new_value');
            $amendment->old_end_date = $request->input('old_end_date');
            $amendment->new_end_date = $request->input('new_end_date');
            $amendment->effective_date = $request->input('effective_date');
            $amendment->ticket_reference = $request->input('ticket_reference');
            $amendment->notes = $request->input('notes');
            $amendment->created_by = auth()->id();

            if (! $amendment->save()) {
                throw new \RuntimeException('Amendment save failed');
            }

            $sideEffectResult = match ($amendmentType) {
                'readjustment' => $contract->applyReadjustment($amendment),
                'renewal'      => $contract->applyRenewal($amendment),
                'termination'  => $contract->applyTermination($amendment),
                'scope_change' => ['type' => 'scope_change'],
                default        => [],
            };
        });

        // ActionLog with side-effect summary
        $log = new Actionlog();
        $log->item_type = ContractAmendment::class;
        $log->item_id = $amendment->id;
        $log->created_by = auth()->id();
        $log->company_id = $contract->company_id;
        $log->log_meta = json_encode($sideEffectResult);
        $log->logaction(ActionType::Update);

        // Build success message with side-effect summary
        $successMsg = trans('admin/contracts/message.amendment.create.success');

        if ($amendmentType === 'readjustment' && isset($sideEffectResult['updated_count'])) {
            $successMsg .= ' ' . trans('admin/contracts/message.amendment.readjustment.auto_update', [
                'count' => $sideEffectResult['updated_count'],
                'old'   => $sideEffectResult['old_value'] ?? '',
                'new'   => $sideEffectResult['new_value'] ?? '',
            ]);
        } elseif ($amendmentType === 'renewal' && isset($sideEffectResult['generated_count'])) {
            $successMsg .= ' ' . trans('admin/contracts/message.amendment.renewal.generated', [
                'count' => $sideEffectResult['generated_count'],
                'date'  => $sideEffectResult['new_end_date'] ?? '',
            ]);
        } elseif ($amendmentType === 'termination' && isset($sideEffectResult['cancelled_count'])) {
            $successMsg .= ' ' . trans('admin/contracts/message.amendment.termination.cancelled', [
                'count' => $sideEffectResult['cancelled_count'],
            ]);
        }

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', $successMsg)
            ->withFragment('amendments');
    }

    /**
     * Show form for editing an amendment (descriptive fields only).
     */
    public function edit(Contract $contract, $amendmentId): View|RedirectResponse
    {
        $amendment = $contract->amendments()->findOrFail($amendmentId);
        $this->authorize('update', $contract);

        return view('contracts/amendments/edit', [
            'contract' => $contract,
            'item'     => $amendment,
        ]);
    }

    /**
     * Update the specified amendment (descriptive fields only, no re-apply of side-effects).
     */
    public function update(Request $request, Contract $contract, $amendmentId): RedirectResponse
    {
        $amendment = $contract->amendments()->findOrFail($amendmentId);
        $this->authorize('update', $contract);

        $request->validate([
            'description'      => 'required|string',
            'ticket_reference' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        $amendment->description = $request->input('description');
        $amendment->ticket_reference = $request->input('ticket_reference');
        $amendment->notes = $request->input('notes');

        if ($amendment->save()) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.amendment.update.success'))
                ->withFragment('amendments');
        }

        return redirect()->back()->withInput()->withErrors($amendment->getErrors());
    }

    /**
     * Delete the given amendment (soft-delete, side-effects NOT reverted).
     */
    public function destroy(Contract $contract, $amendmentId): RedirectResponse
    {
        $amendment = $contract->amendments()->findOrFail($amendmentId);
        $this->authorize('update', $contract);

        $amendment->delete();

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', trans('admin/contracts/message.amendment.delete.success'))
            ->withFragment('amendments');
    }
}

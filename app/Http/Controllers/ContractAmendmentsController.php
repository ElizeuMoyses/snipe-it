<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Helpers\Helper;
use App\Models\Actionlog;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Services\ContractAmendmentPreviewService;
use App\Models\ContractStatusLabel;
use App\Services\Contracts\ContractAuditService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractAmendmentsController extends Controller
{
    public function __construct(private ContractAmendmentPreviewService $previewService)
    {
    }

    /**
     * Show form for creating a new amendment.
     */
    public function create(Contract $contract): View|RedirectResponse
    {
        $this->authorize('update', $contract);

        // Guard: cannot create amendments on terminal contracts
        if ($this->isTerminal($contract)) {
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

        if ($this->isTerminal($contract)) {
            return $this->validationResponse([
                'amendment_type' => [trans('admin/contracts/message.amendment.contract_terminal')],
            ]);
        }

        $validator = $this->previewService->makeValidator($request->all(), $contract);
        if ($validator->fails()) {
            return $this->validationResponse($validator->errors()->toArray());
        }

        return response()->json($this->previewService->build($request->all(), $contract));
    }

    /**
     * Store a newly created amendment with side-effects.
     */
    public function store(Request $request, Contract $contract): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $contract);

        try {
            return $contract->getConnection()->transaction(function () use ($request, $contract) {
                $lockedContract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
                $validator = $this->previewService->makeValidator($request->all(), $lockedContract);

                if ($validator->fails()) {
                    return $this->validationFailure($request, $validator->errors()->toArray());
                }

                $validated = $this->previewService->normalizeValidated(
                    $validator->validated(),
                    $lockedContract
                );
                $tokenErrors = $this->previewService->previewTokenErrors(
                    $request->input('preview_token'),
                    $lockedContract,
                    $validated,
                    true
                );

                if ($tokenErrors) {
                    return $this->validationFailure($request, $tokenErrors);
                }

                return $this->storeLocked($lockedContract, $validated);
            });
        } catch (ValidationException $exception) {
            // Web form submissions keep the application's normal redirect
            // and flashed-input behavior; JSON callers receive a structured
            // 422 instead of the legacy 200 validation envelope.
            if ($request->expectsJson()) {
                return $this->validationResponse($exception->errors());
            }

            throw $exception;
        }
    }

    private function storeLocked(Contract $contract, array $validated): RedirectResponse
    {
        $this->authorize('update', $contract);

        // Guard: cannot create amendments on terminal contracts
        if ($this->isTerminal($contract)) {
            return redirect()->route('contracts.show', $contract->id)
                ->with('error', trans('admin/contracts/message.amendment.contract_terminal'));
        }

        $amendmentType = $validated['amendment_type'];

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

        $amendment = null;
        $sideEffectResult = [];
        $auditService = app(ContractAuditService::class);
        $correlationId = (string) \Illuminate\Support\Str::uuid();

        DB::transaction(function () use ($validated, $contract, $amendmentType, &$amendment, &$sideEffectResult, $auditService, $correlationId) {
            $amendment = new ContractAmendment;
            $amendment->contract_id = $contract->id;
            $amendment->amendment_type = $amendmentType;
            $amendment->description = $validated['description'];
            $amendment->rectifies_amendment_id = $validated['rectifies_amendment_id'];
            $amendment->old_value = $validated['old_value'];
            $amendment->new_value = $validated['new_value'];
            $amendment->old_end_date = $validated['old_end_date'];
            $amendment->new_end_date = $validated['new_end_date'];
            $amendment->effective_date = $validated['effective_date'];
            $amendment->ticket_reference = $validated['ticket_reference'];
            $amendment->notes = $validated['notes'];
            $amendment->created_by = auth()->id();

            if (! $amendment->save()) {
                throw new \RuntimeException('Amendment save failed');
            }

            $auditService->record(
                $contract,
                'amendment.created',
                $amendment,
                [],
                $auditService->snapshot($amendment),
                [
                    'amendment_id' => $amendment->id,
                    'amendment_type' => $amendmentType,
                    'operation' => 'create',
                ],
                $correlationId,
                'amendment-created:'.$amendment->id,
            );

            $sideEffectResult = match ($amendmentType) {
                'readjustment' => $contract->applyReadjustment($amendment),
                'renewal'      => $contract->applyRenewal($amendment),
                'termination'  => $contract->applyTermination($amendment),
                'scope_change' => ['type' => 'scope_change'],
                default        => [],
            };

            $auditService->record(
                $contract,
                'amendment.applied',
                $amendment,
                [],
                $auditService->snapshot($amendment),
                [
                    'amendment_id' => $amendment->id,
                    'amendment_type' => $amendmentType,
                    'effect_type' => $sideEffectResult['type'] ?? $amendmentType,
                    'side_effects' => $sideEffectResult,
                    'documented_only' => $amendmentType === 'scope_change',
                    'operation' => 'apply',
                ],
                $correlationId,
                'amendment-applied:'.$amendment->id,
            );
        });

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
        $request->validate([
            'description'      => 'required|string',
            'ticket_reference' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        return $contract->getConnection()->transaction(function () use ($request, $contract, $amendmentId) {
            $contract = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('update', $contract);
            $amendment = $contract->amendments()->lockForUpdate()->findOrFail($amendmentId);
            $auditService = app(ContractAuditService::class);
            $before = $auditService->snapshot($amendment);
            $amendment->description = $request->input('description');
            $amendment->ticket_reference = $request->input('ticket_reference');
            $amendment->notes = $request->input('notes');

            if ($amendment->save()) {
                if ($amendment->wasChanged()) {
                    $auditService->record(
                        $contract,
                        'amendment.updated',
                        $amendment,
                        $before,
                        $auditService->snapshot($amendment),
                        ['amendment_id' => $amendment->id, 'operation' => 'update'],
                    );
                }
                return redirect()->route('contracts.show', $contract->id)
                    ->with('success', trans('admin/contracts/message.amendment.update.success'))
                    ->withFragment('amendments');
            }

            return redirect()->back()->withInput()->withErrors($amendment->getErrors());
        });
    }

    /**
     * Delete a documentary amendment only. Applied effects require a tracked
     * correction/retification flow owned by the history/exclusion work.
     */
    public function destroy(Contract $contract, $amendmentId): RedirectResponse
    {
        return $contract->getConnection()->transaction(function () use ($contract, $amendmentId) {
            $contract = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('update', $contract);
            $amendment = $contract->amendments()->lockForUpdate()->findOrFail($amendmentId);
            if ($amendment->hasAppliedEffects()) {
                return redirect()->route('contracts.show', $contract->id)
                    ->with('error', trans('admin/contracts/message.amendment.delete.applied'))
                    ->withFragment('amendments');
            }
            $auditService = app(ContractAuditService::class);
            $before = $auditService->snapshot($amendment);
            $amendment->delete();
            $auditService->record(
                $contract,
                'amendment.deleted',
                $amendment,
                $before,
                $auditService->snapshot($amendment),
                ['amendment_id' => $amendment->id, 'operation' => 'delete'],
            );

            return redirect()->route('contracts.show', $contract->id)
                ->with('success', trans('admin/contracts/message.amendment.delete.success'))
                ->withFragment('amendments');
        });
    }

    private function isTerminal(Contract $contract): bool
    {
        return in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'], true);
    }

    private function validationFailure(Request $request, array $errors): JsonResponse
    {
        if ($request->expectsJson()) {
            return $this->validationResponse($errors);
        }

        throw ValidationException::withMessages($errors);
    }

    private function validationResponse(array $errors): JsonResponse
    {
        return response()->json(
            Helper::formatStandardApiResponse('error', null, $errors),
            422
        );
    }
}

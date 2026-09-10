<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActionType;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ContractAmendmentsTransformer;
use App\Models\Actionlog;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Services\ContractAmendmentPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractAmendmentsController extends Controller
{
    public function __construct(private ContractAmendmentPreviewService $previewService)
    {
    }

    /**
     * Display a listing of amendments for a contract.
     */
    public function index(Request $request, Contract $contract): array
    {
        $this->authorize('view', $contract);

        $allowed_columns = [
            'id', 'amendment_type', 'description', 'old_value', 'new_value',
            'old_end_date', 'new_end_date', 'effective_date', 'ticket_reference',
            'notes', 'created_at',
        ];

        $amendments = $contract->amendments()
            ->with('adminuser')
            ->select('contract_amendments.*');

        if ($request->filled('search')) {
            $amendments->where(function ($q) use ($request) {
                $search = $request->input('search');
                $q->where('description', 'like', '%' . $search . '%')
                  ->orWhere('ticket_reference', 'like', '%' . $search . '%')
                  ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('amendment_type')) {
            $amendments->where('amendment_type', $request->input('amendment_type'));
        }

        $offset = (($amendments) && ($request->get('offset') > $amendments->count()))
            ? $amendments->count()
            : app('api_offset_value');
        $limit = app('api_limit_value');
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns)
            ? $request->input('sort')
            : 'effective_date';

        $total = $amendments->count();
        $amendments = $amendments->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        return (new ContractAmendmentsTransformer)->transformContractAmendments($amendments, $total);
    }

    /**
     * Display the specified amendment.
     */
    public function show(Contract $contract, $amendmentId): array
    {
        $this->authorize('view', $contract);
        $amendment = $contract->amendments()
            ->with('adminuser', 'contract.supplier')
            ->findOrFail($amendmentId);

        return (new ContractAmendmentsTransformer)->transformContractAmendment($amendment);
    }

    /**
     * Return the same validated preview contract exposed by the web flow.
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
    public function store(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        return $contract->getConnection()->transaction(function () use ($request, $contract) {
            $lockedContract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $validator = $this->previewService->makeValidator($request->all(), $lockedContract);

            if ($validator->fails()) {
                return $this->validationResponse($validator->errors()->toArray());
            }

            $validated = $this->previewService->normalizeValidated(
                $validator->validated(),
                $lockedContract
            );
            $tokenErrors = $this->previewService->previewTokenErrors(
                $request->input('preview_token'),
                $lockedContract,
                $validated,
                false
            );

            if ($tokenErrors) {
                return $this->validationResponse($tokenErrors);
            }

            return $this->storeLocked($lockedContract, $validated);
        });
    }

    private function storeLocked(Contract $contract, array $validated): JsonResponse
    {
        $this->authorize('update', $contract);

        // Guard: block creation on terminal contracts
        if ($this->isTerminal($contract)) {
            return $this->validationResponse([
                'amendment_type' => [trans('admin/contracts/message.amendment.contract_terminal')],
            ]);
        }

        $amendmentType = $validated['amendment_type'];

        // Guard: readjustment requires active contract
        if ($amendmentType === 'readjustment' && $contract->statusLabel?->meta_type !== 'active') {
            return $this->validationResponse([
                'amendment_type' => [trans('admin/contracts/message.amendment.contract_not_active')],
            ]);
        }

        // Guard: renewal not allowed on draft contracts
        if ($amendmentType === 'renewal' && $contract->statusLabel?->meta_type === 'draft') {
            return $this->validationResponse([
                'amendment_type' => [trans('admin/contracts/message.amendment.not_activatable')],
            ]);
        }

        $amendment = null;
        $sideEffectResult = [];

        DB::transaction(function () use ($validated, $contract, $amendmentType, &$amendment, &$sideEffectResult) {
            $amendment = new ContractAmendment;
            $amendment->contract_id = $contract->id;
            $amendment->amendment_type = $amendmentType;
            $amendment->fill([
                'description' => $validated['description'],
                'old_value' => $validated['old_value'],
                'new_value' => $validated['new_value'],
                'old_end_date' => $validated['old_end_date'],
                'new_end_date' => $validated['new_end_date'],
                'effective_date' => $validated['effective_date'],
                'ticket_reference' => $validated['ticket_reference'],
                'notes' => $validated['notes'],
            ]);
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

        // ActionLog
        $log = new Actionlog();
        $log->item_type = ContractAmendment::class;
        $log->item_id = $amendment->id;
        $log->created_by = auth()->id();
        $log->company_id = $contract->company_id;
        $log->log_meta = json_encode($sideEffectResult);
        $log->logaction(ActionType::Update);

        $responsePayload = array_merge(
            (new ContractAmendmentsTransformer)->transformContractAmendment($amendment),
            ['side_effects' => $sideEffectResult]
        );

        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                $responsePayload,
                trans('admin/contracts/message.amendment.create.success')
            )
        );
    }

    /**
     * Update the specified amendment (descriptive fields only).
     */
    public function update(Request $request, Contract $contract, $amendmentId): JsonResponse
    {
        $this->authorize('update', $contract);
        $amendment = $contract->amendments()->findOrFail($amendmentId);

        $amendment->fill($request->only([
            'description', 'ticket_reference', 'notes',
        ]));

        if ($amendment->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', (new ContractAmendmentsTransformer)->transformContractAmendment($amendment), trans('admin/contracts/message.amendment.update.success'))
            );
        }

        return $this->validationResponse($amendment->getErrors()->toArray());
    }

    /**
     * Remove a documentary amendment. Applied effects require a tracked
     * correction/retification flow owned by the history/exclusion work.
     */
    public function destroy(Contract $contract, $amendmentId): JsonResponse
    {
        $this->authorize('update', $contract);
        $amendment = $contract->amendments()->findOrFail($amendmentId);

        if ($amendment->hasAppliedEffects()) {
            return $this->validationResponse([
                'amendment' => [trans('admin/contracts/message.amendment.delete.applied')],
            ]);
        }

        $amendment->delete();

        return response()->json(
            Helper::formatStandardApiResponse('success', null, trans('admin/contracts/message.amendment.delete.success'))
        );
    }

    private function isTerminal(Contract $contract): bool
    {
        return in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'], true);
    }

    private function validationResponse(array $errors): JsonResponse
    {
        return response()->json(
            Helper::formatStandardApiResponse('error', null, $errors),
            422
        );
    }
}

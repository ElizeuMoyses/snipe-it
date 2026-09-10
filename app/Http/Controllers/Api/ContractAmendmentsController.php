<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActionType;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ContractAmendmentsTransformer;
use App\Models\Actionlog;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractStatusLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractAmendmentsController extends Controller
{
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
     * Store a newly created amendment with side-effects.
     */
    public function store(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);
        return $contract->getConnection()->transaction(function () use ($request, $contract) {
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            return $this->storeLocked($request, $contract);
        });
    }

    private function storeLocked(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        // Guard: block creation on terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.amendment.contract_terminal')),
                422
            );
        }

        $amendmentType = $request->input('amendment_type');

        $baseRules = [
            'amendment_type'   => 'required|in:readjustment,scope_change,renewal,termination',
            'description'      => 'required|string',
            'effective_date'   => 'required|date',
            'old_value'        => 'nullable|numeric|min:0',
            'new_value'        => 'nullable|numeric|min:0',
            'old_end_date'     => 'nullable|date',
            'new_end_date'     => 'nullable|date',
            'ticket_reference' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ];

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

        $request->validate(array_merge($baseRules, $extraRules));

        if ($amendmentType === 'renewal' && $request->date('old_end_date')?->format('Y-m-d') !== $contract->end_date?->format('Y-m-d')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'old_end_date' => trans('validation.in', ['attribute' => 'old_end_date']),
            ]);
        }

        // Guard: readjustment requires active contract
        if ($amendmentType === 'readjustment' && $contract->statusLabel?->meta_type !== 'active') {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.amendment.contract_not_active')),
                422
            );
        }

        // Guard: renewal not allowed on draft contracts
        if ($amendmentType === 'renewal' && $contract->statusLabel?->meta_type === 'draft') {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.amendment.not_activatable')),
                422
            );
        }

        $amendment = null;
        $sideEffectResult = [];

        DB::transaction(function () use ($request, $contract, $amendmentType, &$amendment, &$sideEffectResult) {
            $amendment = new ContractAmendment;
            $amendment->contract_id = $contract->id;
            $amendment->fill($request->only([
                'amendment_type', 'description', 'old_value', 'new_value',
                'old_end_date', 'new_end_date', 'effective_date',
                'ticket_reference', 'notes',
            ]));
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

        $successMsg = trans('admin/contracts/message.amendment.create.success');

        $responsePayload = array_merge(
            (new ContractAmendmentsTransformer)->transformContractAmendment($amendment),
            ['side_effects' => $sideEffectResult]
        );

        return response()->json(
            Helper::formatStandardApiResponse('success', $responsePayload, $successMsg)
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

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $amendment->getErrors())
        );
    }

    /**
     * Remove the specified amendment (soft-delete, side-effects NOT reverted).
     */
    public function destroy(Contract $contract, $amendmentId): JsonResponse
    {
        $this->authorize('update', $contract);
        $amendment = $contract->amendments()->findOrFail($amendmentId);

        $amendment->delete();

        return response()->json(
            Helper::formatStandardApiResponse('success', null, trans('admin/contracts/message.amendment.delete.success'))
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Transformers\ContractInstallmentsTransformer;
use App\Models\Contract;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractInstallmentsController extends Controller
{
    /**
     * Display a listing of installments for a contract.
     */
    public function index(FilterRequest $request, Contract $contract): array
    {
        $this->authorize('view', $contract);

        $allowed_columns = [
            'id', 'installment_number', 'reference_date', 'due_date',
            'expected_value', 'paid_value', 'payment_date', 'payment_method',
            'status_label_id', 'ticket_reference', 'notes', 'created_at',
        ];

        $installments = $contract->installments()
            ->with('statusLabel', 'adminuser')
            ->select('contract_installments.*');

        // Text search
        if ($request->filled('search')) {
            $installments->where(function ($q) use ($request) {
                $search = $request->input('search');
                $q->where('ticket_reference', 'like', '%' . $search . '%')
                  ->orWhere('notes', 'like', '%' . $search . '%')
                  ->orWhere('payment_method', 'like', '%' . $search . '%');
            });
        }

        // Filter by meta_type
        if ($request->filled('meta_type')) {
            $ids = ContractStatusLabel::idsForMetaType('installment', $request->input('meta_type'));
            $installments->whereIn('status_label_id', $ids);
        }

        // Filter by status_label_id
        if ($request->filled('status_label_id')) {
            $installments->where('status_label_id', '=', $request->input('status_label_id'));
        }

        $offset = ($request->input('offset') > $installments->count()) ? $installments->count() : app('api_offset_value');
        $limit = app('api_limit_value');
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'due_date';

        $total = $installments->count();
        $installments = $installments->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        // Avoid N+1: all installments share the same contract — set the relation manually
        $installments->each(fn ($i) => $i->setRelation('contract', $contract));

        return (new ContractInstallmentsTransformer)->transformContractInstallments($installments, $total);
    }

    /**
     * Display the specified installment.
     */
    public function show(Contract $contract, $installmentId): array
    {
        $this->authorize('view', $contract);
        $installment = $contract->installments()
            ->with('statusLabel', 'adminuser', 'contract.supplier')
            ->findOrFail($installmentId);

        return (new ContractInstallmentsTransformer)->transformContractInstallment($installment);
    }

    /**
     * Store a newly created installment.
     */
    public function store(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('installments', $contract);

        // Guard: block creation on terminal contracts
        if (in_array($contract->statusLabel?->meta_type, ['expired', 'cancelled'])) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.contract_terminal')),
                422
            );
        }

        $installment = new ContractInstallment;
        $installment->contract_id = $contract->id;
        $installment->fill($request->only([
            'installment_number', 'reference_date', 'due_date',
            'expected_value', 'notes',
        ]));
        $installment->created_by = auth()->id();

        // Force default pending status — never accept status_label_id from request
        $defaultPending = ContractStatusLabel::defaultForMetaType('installment', 'pending');
        if (! $defaultPending) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.create.missing_default_status')),
                500
            );
        }
        $installment->status_label_id = $defaultPending->id;

        if ($installment->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $installment, trans('admin/contracts/message.installment.create.success'))
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $installment->getErrors())
        );
    }

    /**
     * Update the specified installment.
     */
    public function update(Request $request, Contract $contract, $installmentId): JsonResponse
    {
        $this->authorize('installments', $contract);
        $installment = $contract->installments()->findOrFail($installmentId);

        if ($installment->statusLabel?->isTerminal()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.terminal_locked')),
                422
            );
        }

        // Only allow editing basic fields — payment fields exclusive to payment flow
        $installment->fill($request->only([
            'installment_number', 'reference_date', 'due_date',
            'expected_value', 'notes',
        ]));

        if ($installment->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $installment, trans('admin/contracts/message.installment.update.success'))
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $installment->getErrors())
        );
    }

    /**
     * Register payment for an installment (POST).
     */
    public function storePayment(Request $request, Contract $contract, $installmentId): JsonResponse
    {
        return $contract->getConnection()->transaction(function () use ($request, $contract, $installmentId) {
            $this->authorize('installments', $contract);
            $contract = Contract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $installment = $contract->installments()->lockForUpdate()->findOrFail($installmentId);

            // Guard: only pending and overdue can receive payment
            if ($installment->statusLabel?->isTerminal()) {
                return response()->json(
                    Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.payment.already_terminal')),
                    422
                );
            }

            $allowedMeta = ['pending', 'overdue'];
            if (! in_array($installment->statusLabel?->meta_type, $allowedMeta)) {
                return response()->json(
                    Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.payment.already_terminal')),
                    422
                );
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
                return response()->json(
                    Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.payment.missing_default_status')),
                    500
                );
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
                return response()->json(
                    Helper::formatStandardApiResponse('success', $installment, trans('admin/contracts/message.installment.payment.success'))
                );
            }

            return response()->json(
                Helper::formatStandardApiResponse('error', null, $installment->getErrors())
            );
        });
    }

    /**
     * Remove the specified installment.
     */
    public function destroy(Contract $contract, $installmentId): JsonResponse
    {
        $this->authorize('installments', $contract);
        $installment = $contract->installments()->findOrFail($installmentId);

        if ($installment->statusLabel?->isTerminal()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.installment.terminal_locked')),
                422
            );
        }

        $installment->delete();

        return response()->json(
            Helper::formatStandardApiResponse('success', null, trans('admin/contracts/message.installment.delete.success'))
        );
    }
}

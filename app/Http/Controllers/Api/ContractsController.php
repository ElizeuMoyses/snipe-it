<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Transformers\ContractsTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractsController extends Controller
{
    /**
     * Display a listing of contracts.
     */
    public function index(FilterRequest $request): array
    {
        $this->authorize('view', Contract::class);

        $allowed_columns = [
            'id',
            'name',
            'contract_number',
            'contract_type',
            'start_date',
            'end_date',
            'billing_cycle',
            'installment_value',
            'total_value',
            'total_installments',
            'notes',
            'created_at',
            'updated_at',
        ];

        $contracts = Contract::select([
            'id', 'name', 'contract_number', 'contract_type', 'status_label_id',
            'supplier_id', 'company_id', 'start_date', 'end_date', 'billing_cycle',
            'installment_value', 'total_value', 'total_installments', 'notes',
            'created_at', 'created_by', 'updated_at', 'deleted_at',
        ])
            ->with('supplier', 'company', 'statusLabel', 'adminuser')
            ->withCount('installments');

        if ($request->filled('filter') || $request->filled('search')) {
            $contracts->TextSearch($request->input('filter') ?: $request->input('search'));
        }

        if ($request->filled('supplier_id')) {
            $contracts->where('supplier_id', '=', $request->input('supplier_id'));
        }

        if ($request->filled('company_id')) {
            $contracts->where('company_id', '=', $request->input('company_id'));
        }

        if ($request->filled('contract_type')) {
            $contracts->where('contract_type', '=', $request->input('contract_type'));
        }

        if ($request->filled('status_label_id')) {
            $contracts->where('status_label_id', '=', $request->input('status_label_id'));
        }

        if ($request->filled('meta_type')) {
            $statusIds = ContractStatusLabel::idsForMetaType('contract', $request->input('meta_type'));
            $contracts->whereIn('status_label_id', $statusIds);
        }

        // Make sure the offset and limit are actually integers and do not exceed system limits
        $offset = ($request->input('offset') > $contracts->count()) ? $contracts->count() : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';

        switch ($request->input('sort')) {
            case 'created_by':
                $contracts->OrderByCreatedByName($order);
                break;
            default:
                $contracts->orderBy($sort, $order);
                break;
        }

        $total = $contracts->count();
        $contracts = $contracts->skip($offset)->take($limit)->get();

        return (new ContractsTransformer)->transformContracts($contracts, $total);
    }

    /**
     * Store a newly created contract.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Contract::class);

        $contract = new Contract;
        $contract->fill($request->all());
        $contract->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $contract->created_by = auth()->id();

        // Set default status (draft) if not provided
        if (! $request->filled('status_label_id')) {
            $defaultStatus = ContractStatusLabel::defaultForMetaType('contract', 'draft');
            $contract->status_label_id = $defaultStatus?->id;
        }

        if ($contract->save()) {
            // Generate installments if contract is recurring
            if ($contract->contract_type === 'recurring' && $contract->start_date && $contract->end_date) {
                $contract->generateInstallments();
            }

            return response()->json(Helper::formatStandardApiResponse('success', $contract, trans('admin/contracts/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $contract->getErrors()));
    }

    /**
     * Display the specified contract.
     */
    public function show($id): array
    {
        $this->authorize('view', Contract::class);
        $contract = Contract::with('supplier', 'company', 'statusLabel', 'adminuser', 'installments')
            ->withCount('installments')
            ->findOrFail($id);

        return (new ContractsTransformer)->transformContract($contract);
    }

    /**
     * Update the specified contract.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $this->authorize('update', Contract::class);

        $contract = Contract::findOrFail($id);
        $contract->fill($request->all());
        $contract->company_id = Company::getIdForCurrentUser($request->input('company_id'));

        if ($contract->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $contract, trans('admin/contracts/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $contract->getErrors()));
    }

    /**
     * Remove the specified contract.
     */
    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);

        if (! $contract->isDeletable()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.assoc_installments')));
        }

        $contract->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/contracts/message.delete.success')));
    }

    /**
     * Gets a paginated collection for the select2 menus.
     */
    public function selectlist(Request $request): array
    {
        $this->authorize('view.selectlists');

        $contracts = Contract::select([
            'id',
            'name',
            'contract_number',
        ]);

        if ($request->filled('search')) {
            $contracts = $contracts->where(function ($query) use ($request) {
                $query->where('contracts.name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('contracts.contract_number', 'LIKE', '%'.$request->input('search').'%');
            });
        }

        $contracts = $contracts->orderBy('name', 'ASC')->paginate(50);

        foreach ($contracts as $contract) {
            $contract->use_text = $contract->name.' ('.$contract->contract_number.')';
        }

        return (new SelectlistTransformer)->transformSelectlist($contracts);
    }
}

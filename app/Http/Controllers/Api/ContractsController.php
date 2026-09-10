<?php

namespace App\Http\Controllers\Api;

use App\Actions\Contracts\ContractLifecycleAction;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Transformers\ContractAuditTransformer;
use App\Http\Transformers\ContractsTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Services\Contracts\ContractAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'billing_day',
            'installment_value',
            'total_value',
            'total_installments',
            'notes',
            'created_at',
            'updated_at',
        ];

        $contracts = ($request->boolean('archived') || $request->boolean('deleted'))
            ? Contract::onlyTrashed()
            : Contract::query();

        $contracts = $contracts->select([
            'id', 'name', 'contract_number', 'contract_type', 'status_label_id',
            'supplier_id', 'company_id', 'start_date', 'end_date', 'billing_cycle',
            'billing_day', 'installment_value', 'total_value', 'total_installments', 'notes',
            'created_at', 'created_by', 'updated_at', 'deleted_at',
        ])
            ->with('supplier', 'company', 'statusLabel', 'adminuser')
            ->withCount([
                'installments',
                'installments as pending_installments_count' => fn ($query) => $query->withTrashed()->pending(),
                'amendments as amendments_count' => fn ($query) => $query->withTrashed(),
            ]);

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

        return $contract->getConnection()->transaction(function () use ($request, $contract) {
            if ($contract->save()) {
                app(ContractAuditService::class)->record(
                    $contract,
                    'contract.created',
                    $contract,
                    [],
                    app(ContractAuditService::class)->snapshot($contract),
                );

                // Preserve the API default while allowing callers to defer generation, like the UI.
                if ($request->boolean('auto_generate_installments', true) && $contract->start_date) {
                    if ($contract->contract_type === 'recurring'
                        || ($contract->contract_type === 'one_time'
                            && ($contract->total_value > 0 || $contract->installment_value > 0))) {
                        $contract->generateInstallments();
                    }
                }

                return response()->json(Helper::formatStandardApiResponse('success', $contract, trans('admin/contracts/message.create.success')));
            }

            return response()->json(Helper::formatStandardApiResponse('error', null, $contract->getErrors()));
        });
    }

    /**
     * Display the specified contract.
     */
    public function show($id): array
    {
        $this->authorize('view', Contract::class);
        $contract = Contract::withTrashed()
            ->with([
                'supplier',
                'company',
                'statusLabel',
                'adminuser',
                'installments' => fn ($query) => $query->withTrashed(),
                'amendments' => fn ($query) => $query->withTrashed(),
            ])
            ->withCount([
                'installments',
                'installments as pending_installments_count' => fn ($query) => $query->withTrashed()->pending(),
                'amendments as amendments_count' => fn ($query) => $query->withTrashed(),
            ])
            ->findOrFail($id);

        return (new ContractsTransformer)->transformContract($contract);
    }

    /**
     * Update the specified contract.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $this->authorize('update', Contract::class);

        return DB::transaction(function () use ($request, $id) {
            $contract = Contract::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $contract);
            $before = app(ContractAuditService::class)->snapshot($contract);
            $contract->fill($request->all());
            $contract->company_id = Company::getIdForCurrentUser($request->input('company_id'));
            $changed = $contract->isDirty();

            if ($contract->save()) {
                if ($changed) {
                    app(ContractAuditService::class)->record(
                        $contract,
                        'contract.updated',
                        $contract,
                        $before,
                        app(ContractAuditService::class)->snapshot($contract),
                    );
                }

                return response()->json(Helper::formatStandardApiResponse('success', $contract, trans('admin/contracts/message.update.success')));
            }

            return response()->json(Helper::formatStandardApiResponse('error', null, $contract->getErrors()));
        });
    }

    /**
     * Remove the specified contract.
     */
    public function destroy(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $result = app(ContractLifecycleAction::class)->archive($contract, $validated['reason']);

        if ($result['status'] === 'blocked_paid') {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.archive.blocked_paid', [
                'count' => $result['paid_count'],
            ])), 422);
        }

        if ($result['status'] !== 'archived') {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.archive.already_archived')), 422);
        }

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/contracts/message.archive.success')));
    }

    /**
     * Restore an archived contract without recreating dependent records.
     */
    public function restore(Request $request, $id): JsonResponse
    {
        $contract = Contract::withTrashed()->findOrFail($id);
        $this->authorize('restore', $contract);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'min:3', 'max:2000'],
        ]);

        $result = app(ContractLifecycleAction::class)->restore($contract, $validated['reason'] ?? null);

        if ($result['status'] === 'restore_conflict') {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.archive.restore_conflict')), 422);
        }

        if ($result['status'] !== 'restored') {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/contracts/message.archive.not_archived')), 422);
        }

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/contracts/message.archive.restored')));
    }

    public function history(Request $request, $contractId): JsonResponse
    {
        $contract = Contract::withTrashed()->findOrFail($contractId);
        $this->authorize('history', $contract);

        $validated = $request->validate([
            'action' => 'nullable|string|max:80',
            'action_type' => 'nullable|string|max:80',
            'entity' => 'nullable|string|max:80',
            'created_by' => 'nullable|integer|min:1',
            'source' => 'nullable|string|max:20',
            'search' => 'nullable|string|max:255',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $page = app(ContractAuditService::class)->historyFor(
            $contract,
            $validated,
            (int) ($validated['offset'] ?? 0),
            (int) ($validated['limit'] ?? 50),
        );

        return response()->json(
            (new ContractAuditTransformer)->transform($page['rows'], $page['total']),
            200,
            ['Content-Type' => 'application/json;charset=utf8'],
            JSON_UNESCAPED_UNICODE,
        );
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

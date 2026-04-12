<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Transformers\ContractStatusLabelsTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractStatusLabelsController extends Controller
{
    /**
     * Display a listing of contract status labels.
     */
    public function index(FilterRequest $request): array
    {
        $this->authorize('view', Contract::class);

        $allowed_columns = [
            'id',
            'name',
            'scope',
            'meta_type',
            'color',
            'icon',
            'sort_order',
            'is_default',
            'notes',
            'created_at',
        ];

        $statusLabels = ContractStatusLabel::select([
            'id', 'name', 'scope', 'meta_type', 'color', 'icon',
            'sort_order', 'is_default', 'notes', 'created_at', 'created_by', 'updated_at', 'deleted_at',
        ])->with('adminuser');

        if ($request->filled('filter') || $request->filled('search')) {
            $statusLabels->TextSearch($request->input('filter') ?: $request->input('search'));
        }

        if ($request->filled('scope')) {
            $statusLabels->where('scope', '=', $request->input('scope'));
        }

        if ($request->filled('meta_type')) {
            $statusLabels->where('meta_type', '=', $request->input('meta_type'));
        }

        // Make sure the offset and limit are actually integers and do not exceed system limits
        $offset = ($request->input('offset') > $statusLabels->count()) ? $statusLabels->count() : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'sort_order';

        $statusLabels->orderBy($sort, $order);

        $total = $statusLabels->count();
        $statusLabels = $statusLabels->skip($offset)->take($limit)->get();

        return (new ContractStatusLabelsTransformer)->transformContractStatusLabels($statusLabels, $total);
    }

    /**
     * Store a newly created contract status label.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Contract::class);

        $statusLabel = new ContractStatusLabel;
        $statusLabel->fill($request->all());
        $statusLabel->created_by = auth()->id();

        // If this label is set as default, clear other defaults for same scope+meta_type
        if ($request->input('is_default')) {
            ContractStatusLabel::where('scope', $request->input('scope'))
                ->where('meta_type', $request->input('meta_type'))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        if ($statusLabel->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $statusLabel, trans('admin/contract_status_labels/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $statusLabel->getErrors()));
    }

    /**
     * Display the specified contract status label.
     */
    public function show($id): array
    {
        $this->authorize('view', Contract::class);
        $statusLabel = ContractStatusLabel::with('adminuser')->findOrFail($id);

        return (new ContractStatusLabelsTransformer)->transformContractStatusLabel($statusLabel);
    }

    /**
     * Update the specified contract status label.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $this->authorize('update', Contract::class);

        $statusLabel = ContractStatusLabel::findOrFail($id);
        $statusLabel->fill($request->all());

        // If this label is set as default, clear other defaults for same scope+meta_type
        if ($request->input('is_default')) {
            ContractStatusLabel::where('scope', $statusLabel->scope)
                ->where('meta_type', $statusLabel->meta_type)
                ->where('is_default', true)
                ->where('id', '!=', $statusLabel->id)
                ->update(['is_default' => false]);
        }

        if ($statusLabel->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $statusLabel, trans('admin/contract_status_labels/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $statusLabel->getErrors()));
    }

    /**
     * Remove the specified contract status label.
     */
    public function destroy($id): JsonResponse
    {
        $this->authorize('delete', Contract::class);

        $statusLabel = ContractStatusLabel::findOrFail($id);

        if (! $statusLabel->isDeletable()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/contract_status_labels/message.assoc_contracts')));
        }

        $statusLabel->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/contract_status_labels/message.delete.success')));
    }

    /**
     * Gets a paginated collection for the select2 menus.
     */
    public function selectlist(Request $request): array
    {
        $this->authorize('view.selectlists');

        $statusLabels = ContractStatusLabel::select([
            'id',
            'name',
            'scope',
            'meta_type',
            'color',
        ]);

        if ($request->filled('scope')) {
            $statusLabels->where('scope', '=', $request->input('scope'));
        }

        if ($request->filled('search')) {
            $statusLabels = $statusLabels->where('name', 'LIKE', '%'.$request->input('search').'%');
        }

        $statusLabels = $statusLabels->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->paginate(50);

        foreach ($statusLabels as $statusLabel) {
            $statusLabel->use_text = $statusLabel->name;
        }

        return (new SelectlistTransformer)->transformSelectlist($statusLabels);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Transformers\ContractTypesTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractTypesController extends Controller
{
    public function index(FilterRequest $request): array
    {
        $this->authorize('view', ContractType::class);

        $allowedColumns = ['id', 'name', 'code', 'is_active', 'created_at', 'updated_at'];
        $types = ContractType::with('adminuser')->withCount('contracts');

        if ($request->filled('filter') || $request->filled('search')) {
            $types->where(function ($query) use ($request) {
                $term = $request->input('filter') ?: $request->input('search');
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('code', 'LIKE', '%'.$term.'%');
            });
        }

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowedColumns, true)
            ? $request->input('sort')
            : 'name';
        $total = $types->count();
        $offset = min((int) $request->input('offset', 0), $total);
        $types = $types->orderBy($sort, $order)
            ->skip($offset)
            ->take(app('api_limit_value'))
            ->get();

        return (new ContractTypesTransformer)->transformContractTypes($types, $total);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ContractType::class);

        $type = new ContractType;
        $type->fill($this->input($request));
        $type->created_by = auth()->id();

        if ($type->save()) {
            return response()->json(Helper::formatStandardApiResponse(
                'success',
                $type,
                trans('admin/contract_types/message.create.success')
            ));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $type->getErrors()));
    }

    public function show($id): array
    {
        $this->authorize('view', ContractType::class);

        return (new ContractTypesTransformer)->transformContractType(
            ContractType::with('adminuser')->withCount('contracts')->findOrFail($id)
        );
    }

    public function update(Request $request, $id): JsonResponse
    {
        $type = ContractType::findOrFail($id);
        $this->authorize('update', $type);
        $type->fill($this->input($request, $type));

        if ($type->save()) {
            return response()->json(Helper::formatStandardApiResponse(
                'success',
                $type,
                trans('admin/contract_types/message.update.success')
            ));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $type->getErrors()));
    }

    public function destroy($id): JsonResponse
    {
        $type = ContractType::findOrFail($id);
        $this->authorize('delete', $type);

        if (! $type->isDeletable()) {
            return response()->json(Helper::formatStandardApiResponse(
                'error',
                null,
                trans('admin/contract_types/message.assoc_contracts')
            ));
        }

        $type->delete();

        return response()->json(Helper::formatStandardApiResponse(
            'success',
            null,
            trans('admin/contract_types/message.delete.success')
        ));
    }

    public function selectlist(Request $request): array
    {
        $this->authorize('view.selectlists');

        $types = ContractType::active()->select(['id', 'name', 'code']);
        if ($request->filled('search')) {
            $types->where(function ($query) use ($request) {
                $term = $request->input('search');
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('code', 'LIKE', '%'.$term.'%');
            });
        }

        $types = $types->orderBy('name')->paginate(50);
        foreach ($types as $type) {
            $type->use_text = $type->name;
        }

        return (new SelectlistTransformer)->transformSelectlist($types);
    }

    private function input(Request $request, ?ContractType $type = null): array
    {
        $input = $request->only(['name', 'code', 'is_active', 'notes']);
        if ($request->filled('code')) {
            $input['code'] = Str::lower(trim((string) $request->input('code')));
        } elseif (! $type) {
            $input['code'] = Str::slug((string) $request->input('name'), '_');
        } else {
            $input['code'] = $type->code;
        }

        if ($request->has('is_active') || ! $type) {
            $input['is_active'] = $request->has('is_active')
                ? $request->boolean('is_active')
                : true;
        }

        return $input;
    }
}

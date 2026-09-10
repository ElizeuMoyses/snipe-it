<?php

namespace App\Http\Transformers;

use App\Models\ContractType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ContractTypesTransformer
{
    public function transformContractTypes(Collection $types, $total): array
    {
        $rows = [];
        foreach ($types as $type) {
            $rows[] = $this->transformContractType($type);
        }

        return (new DatatablesTransformer)->transformDatatables($rows, $total);
    }

    public function transformContractType(?ContractType $type = null): array
    {
        if (! $type) {
            return [];
        }

        return [
            'id' => (int) $type->id,
            'name' => e($type->name),
            'code' => e($type->code),
            'is_active' => (bool) $type->is_active,
            'contracts_count' => (int) ($type->contracts_count ?? $type->contracts()->count()),
            'created_by' => $type->adminuser ? [
                'id' => (int) $type->adminuser->id,
                'name' => e($type->adminuser->present()->fullName),
            ] : null,
            'created_at' => optional($type->created_at)->toIso8601String(),
            'updated_at' => optional($type->updated_at)->toIso8601String(),
            'available_actions' => [
                'update' => Gate::allows('update', $type),
                'delete' => Gate::allows('delete', $type) && $type->isDeletable(),
            ],
        ];
    }
}

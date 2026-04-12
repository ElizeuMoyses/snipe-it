<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ContractStatusLabelsTransformer
{
    public function transformContractStatusLabels(Collection $statusLabels, $total)
    {
        $array = [];
        foreach ($statusLabels as $statusLabel) {
            $array[] = self::transformContractStatusLabel($statusLabel);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformContractStatusLabel(?ContractStatusLabel $statusLabel = null)
    {
        if ($statusLabel) {
            $array = [
                'id'         => (int) $statusLabel->id,
                'name'       => e($statusLabel->name),
                'scope'      => e($statusLabel->scope),
                'meta_type'  => e($statusLabel->meta_type),
                'color'      => $statusLabel->color ? e($statusLabel->color) : null,
                'icon'       => $statusLabel->icon ? e($statusLabel->icon) : null,
                'sort_order' => (int) $statusLabel->sort_order,
                'is_default' => (bool) $statusLabel->is_default,
                'notes'      => e($statusLabel->notes),
                'created_by' => $statusLabel->adminuser ? [
                    'id'   => (int) $statusLabel->adminuser->id,
                    'name' => e($statusLabel->adminuser->present()->fullName),
                ] : null,
                'created_at' => Helper::getFormattedDateObject($statusLabel->created_at, 'datetime'),
                'updated_at' => Helper::getFormattedDateObject($statusLabel->updated_at, 'datetime'),
            ];

            $permissions_array['available_actions'] = [
                'update' => Gate::allows('update', Contract::class),
                'delete' => Gate::allows('delete', Contract::class) && $statusLabel->isDeletable(),
            ];

            $array += $permissions_array;

            return $array;
        }
    }
}

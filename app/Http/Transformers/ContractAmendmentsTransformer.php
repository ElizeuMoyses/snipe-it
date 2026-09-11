<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Contract;
use App\Models\ContractAmendment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ContractAmendmentsTransformer
{
    public function transformContractAmendments(Collection $amendments, $total)
    {
        $array = [];
        foreach ($amendments as $amendment) {
            $array[] = self::transformContractAmendment($amendment);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformContractAmendment(?ContractAmendment $amendment = null)
    {
        if (! $amendment) {
            return [];
        }

        $array = [
            'id'                => (int) $amendment->id,
            'contract'          => $amendment->contract ? [
                'id'   => (int) $amendment->contract->id,
                'name' => e($amendment->contract->name),
            ] : null,
            'amendment_type'    => e($amendment->amendment_type),
            'description'       => e($amendment->description),
            'rectifies_amendment_id' => $amendment->rectifies_amendment_id,
            'old_value'         => $amendment->old_value ? Helper::formatCurrencyOutput($amendment->old_value) : null,
            'new_value'         => $amendment->new_value ? Helper::formatCurrencyOutput($amendment->new_value) : null,
            'old_end_date'      => $amendment->old_end_date ? Helper::getFormattedDateObject($amendment->old_end_date, 'date') : null,
            'new_end_date'      => $amendment->new_end_date ? Helper::getFormattedDateObject($amendment->new_end_date, 'date') : null,
            'effective_date'    => Helper::getFormattedDateObject($amendment->effective_date, 'date'),
            'ticket_reference'  => $amendment->ticket_reference ? e($amendment->ticket_reference) : null,
            'notes'             => $amendment->notes ? Helper::parseEscapedMarkedownInline($amendment->notes) : null,
            'created_by'        => $amendment->adminuser ? [
                'id'   => (int) $amendment->adminuser->id,
                'name' => e($amendment->adminuser->present()->fullName),
            ] : null,
            'created_at'        => Helper::getFormattedDateObject($amendment->created_at, 'datetime'),
            'updated_at'        => Helper::getFormattedDateObject($amendment->updated_at, 'datetime'),
        ];

        $permissions_array['available_actions'] = [
            'update' => Gate::allows('update', Contract::class),
            'delete' => Gate::allows('update', Contract::class),
        ];

        $array += $permissions_array;

        return $array;
    }
}

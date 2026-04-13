<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Contract;
use App\Models\ContractInstallment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ContractInstallmentsTransformer
{
    public function transformContractInstallments(Collection $installments, $total)
    {
        $array = [];
        foreach ($installments as $installment) {
            $array[] = self::transformContractInstallment($installment);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformContractInstallment(?ContractInstallment $installment = null)
    {
        if ($installment) {
            $array = [
                'id'                 => (int) $installment->id,
                'contract'           => $installment->contract ? [
                    'id'   => (int) $installment->contract->id,
                    'name' => e($installment->contract->name),
                ] : null,
                'installment_number' => (int) $installment->installment_number,
                'reference_date'     => Helper::getFormattedDateObject($installment->reference_date, 'date'),
                'due_date'           => Helper::getFormattedDateObject($installment->due_date, 'date'),
                'expected_value'     => Helper::formatCurrencyOutput($installment->expected_value),
                'paid_value'         => $installment->paid_value !== null ? Helper::formatCurrencyOutput($installment->paid_value) : null,
                'payment_date'       => Helper::getFormattedDateObject($installment->payment_date, 'date'),
                'payment_method'     => $installment->payment_method ? e($installment->payment_method) : null,
                'status_label'       => $installment->statusLabel ? [
                    'id'        => (int) $installment->statusLabel->id,
                    'name'      => e($installment->statusLabel->name),
                    'meta_type' => e($installment->statusLabel->meta_type),
                    'color'     => e($installment->statusLabel->color),
                    'icon'      => e($installment->statusLabel->icon),
                ] : null,
                'ticket_reference'   => $installment->ticket_reference ? e($installment->ticket_reference) : null,
                'notes'              => $installment->notes ? Helper::parseEscapedMarkedownInline($installment->notes) : null,
                'created_by'         => $installment->adminuser ? [
                    'id'   => (int) $installment->adminuser->id,
                    'name' => e($installment->adminuser->present()->fullName),
                ] : null,
                'created_at'         => Helper::getFormattedDateObject($installment->created_at, 'datetime'),
                'updated_at'         => Helper::getFormattedDateObject($installment->updated_at, 'datetime'),
            ];

            $permissions_array['available_actions'] = [
                'update' => Gate::allows('installments', Contract::class),
                'delete' => Gate::allows('installments', Contract::class)
                            && ! $installment->statusLabel?->isTerminal(),
            ];

            $array += $permissions_array;

            return $array;
        }
    }
}

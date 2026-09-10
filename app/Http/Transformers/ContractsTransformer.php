<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ContractsTransformer
{
    public function transformContracts(Collection $contracts, $total)
    {
        $array = [];
        foreach ($contracts as $contract) {
            $array[] = self::transformContract($contract);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformContract(?Contract $contract = null)
    {
        if ($contract) {
            $array = [
                'id' => (int) $contract->id,
                'name' => e($contract->name),
                'contract_number' => e($contract->contract_number),
                'contract_type' => $contract->contract_type
                    ? trans('admin/contracts/general.type_'.$contract->contract_type)
                    : null,
                'status_label' => $contract->statusLabel ? [
                    'id' => (int) $contract->statusLabel->id,
                    'name' => e($contract->statusLabel->name),
                    'meta_type' => e($contract->statusLabel->meta_type),
                    'color' => e($contract->statusLabel->color),
                    'icon' => e($contract->statusLabel->icon),
                ] : null,
                'supplier' => $contract->supplier ? [
                    'id' => (int) $contract->supplier->id,
                    'name' => e($contract->supplier->name),
                ] : null,
                'company' => $contract->company ? [
                    'id' => (int) $contract->company->id,
                    'name' => e($contract->company->name),
                ] : null,
                'start_date' => Helper::getFormattedDateObject($contract->start_date, 'date'),
                'end_date' => Helper::getFormattedDateObject($contract->end_date, 'date'),
                'billing_cycle' => e($contract->billing_cycle),
                'billing_day' => $contract->billing_day,
                'installment_value' => $contract->installment_value ? Helper::formatCurrencyOutput($contract->installment_value) : null,
                'total_value' => $contract->total_value ? Helper::formatCurrencyOutput($contract->total_value) : null,
                'total_installments' => $contract->total_installments ? (int) $contract->total_installments : null,
                'installments_count' => (int) ($contract->installments_count ?? $contract->installments()->count()),
                'pending_installments_count' => (int) ($contract->pending_installments_count ?? $contract->installments()->withTrashed()->pending()->count()),
                'amendments_count' => (int) ($contract->amendments_count ?? $contract->amendments()->withTrashed()->count()),
                'deleted_at' => $contract->deleted_at?->toIso8601String(),
                'is_archived' => $contract->trashed(),
                'notes' => $contract->notes ? Helper::parseEscapedMarkedownInline($contract->notes) : null,
                'created_by' => $contract->adminuser ? [
                    'id' => (int) $contract->adminuser->id,
                    'name' => e($contract->adminuser->present()->fullName),
                ] : null,
                'created_at' => Helper::getFormattedDateObject($contract->created_at, 'datetime'),
                'updated_at' => Helper::getFormattedDateObject($contract->updated_at, 'datetime'),
            ];

            $permissions_array['available_actions'] = [
                'update' => Gate::allows('update', $contract),
                'delete' => Gate::allows('delete', $contract) && $contract->isDeletable(),
                'restore' => $contract->trashed() && Gate::allows('restore', $contract),
            ];

            $array += $permissions_array;

            return $array;
        }
    }
}

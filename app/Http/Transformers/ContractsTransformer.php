<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Contract;
use App\Services\ContractMoney;
use App\Services\ContractFinancialSummary;
use Carbon\Carbon;
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
            $nextDueDate = $contract->getAttribute('next_due_date');
            if ($nextDueDate === null && $contract->relationLoaded('installments')) {
                $nextDueDate = (new ContractFinancialSummary)->summarize($contract)['next_due_date'];
            }

            $array = [
                'id' => (int) $contract->id,
                'name' => e($contract->name),
                'contract_number' => e($contract->contract_number),
                'contract_type' => $contract->contract_type
                    ? trans('admin/contracts/general.type_'.$contract->contract_type)
                    : null,
                // Keep contract_type as the legacy billing-modality field;
                // classification is now independently configurable.
                'billing_modality'  => $contract->contract_type,
                'contract_type_id'   => $contract->contract_type_id ? (int) $contract->contract_type_id : null,
                'contract_classification' => $contract->contractType ? [
                    'id'   => (int) $contract->contractType->id,
                    'name' => e($contract->contractType->name),
                    'code' => e($contract->contractType->code),
                ] : null,
                'status_label'      => $contract->statusLabel ? [
                    'id'        => (int) $contract->statusLabel->id,
                    'name'      => e($contract->statusLabel->name),
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
                'start_date'        => self::formatDate($contract->start_date),
                'end_date'          => self::formatDate($contract->end_date),
                'validity'          => trans('admin/contracts/general.validity_' . self::validityMeta($contract)),
                'next_due_date'     => self::formatDate($nextDueDate),
                'billing_cycle'     => e($contract->billing_cycle),
                'billing_day'       => $contract->billing_day,
                'installment_value' => ContractMoney::toDecimal($contract->installment_value),
                'installment_value_formatted' => self::formatAmount($contract->installment_value),
                'total_value'       => ContractMoney::toDecimal($contract->total_value),
                'total_value_formatted' => self::formatAmount($contract->total_value),
                'total_value_mode'  => $contract->total_value_mode ?: 'automatic',
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

    private static function formatAmount(mixed $amount): ?string
    {
        $cents = ContractMoney::toCents($amount);

        return $cents === null ? null : ContractMoney::centsToBr($cents);
    }

    private static function formatDate($date): ?array
    {
        $formatted = Helper::getFormattedDateObject($date, 'date');

        if (is_array($formatted)
            && in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true)
            && isset($formatted['date'])) {
            $formatted['formatted'] = Carbon::parse($formatted['date'])->format('d/m/Y');
        }

        return is_array($formatted) ? $formatted : null;
    }

    private static function formatMoney(mixed $value): string
    {
        if (in_array(app()->getLocale(), ['pt-BR', 'pt_BR'], true)) {
            return ContractFinancialSummary::formatCents(
                ContractFinancialSummary::toCents($value),
                trans('general.currency')
            );
        }

        return Helper::formatCurrencyOutput($value);
    }

    private static function validityMeta(Contract $contract): string
    {
        $today = Carbon::today();
        $startDate = $contract->start_date ? Carbon::parse($contract->start_date) : null;
        $endDate = $contract->end_date ? Carbon::parse($contract->end_date) : null;

        if ($endDate === null) {
            return 'indefinite';
        }

        if ($endDate->lt($today)) {
            return 'expired';
        }

        if ($startDate && $startDate->gt($today)) {
            return 'future';
        }

        return $endDate->lte($today->copy()->addDays(30)) ? 'expiring' : 'current';
    }
}

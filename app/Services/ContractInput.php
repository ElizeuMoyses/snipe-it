<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractStatusLabel;
use App\Models\ContractType;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Shared input contract for the web and API contract endpoints.
 */
final class ContractInput
{
    private const MONEY_FIELDS = ['installment_value', 'total_value'];

    public static function validate(array $input, bool $creating, ?Contract $contract = null, bool $localized = true): array
    {
        // A browser submits empty controls for legacy fields that did not
        // exist when the contract was created. Treat those empty controls as
        // omitted, while keeping an explicit JSON null meaningful for PATCH.
        if (! $creating && $contract && self::isLegacy($contract)) {
            foreach (['contract_type_id', 'supplier_id', 'company_id', 'end_date', 'billing_day', 'description'] as $field) {
                if (array_key_exists($field, $input) && $input[$field] === '') {
                    unset($input[$field]);
                }
            }
        }

        [$data, $invalidMoneyFields] = self::normalize($input, $localized);
        $validator = Validator::make(
            $data,
            self::rules($creating, $contract, $data),
            self::messages(),
            self::attributes()
        );

        $validator->after(function ($validator) use ($data, $invalidMoneyFields, $creating, $contract) {
            foreach ($invalidMoneyFields as $field) {
                $validator->errors()->add($field, self::messages()['money.invalid']);
            }

            self::validateReferences($validator, $data);
            self::validateSchedule($validator, $data, $creating, $contract);
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $effective = self::effectiveData($data, $contract);
        $draft = self::draftContract($effective);
        $mode = $effective['total_value_mode'] ?? 'automatic';

        if ($mode === 'automatic' && self::shouldRefreshAutomaticTotal($data, $creating)) {
            $data['total_value'] = $draft->installmentPreview()['planned_total'];
        }

        if ($creating && ! array_key_exists('total_value_mode', $data)) {
            $data['total_value_mode'] = 'automatic';
        }

        return $data;
    }

    public static function validatePreview(array $input, bool $localized = true): Contract
    {
        [$data, $invalidMoneyFields] = self::normalize($input, $localized);
        $validator = Validator::make(
            $data,
            self::previewRules($data),
            self::messages(),
            self::attributes()
        );
        $validator->after(function ($validator) use ($data, $invalidMoneyFields) {
            foreach ($invalidMoneyFields as $field) {
                $validator->errors()->add($field, self::messages()['money.invalid']);
            }

            self::validateSchedule($validator, $data, true, null);
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $contract = self::draftContract($data);
        if (($data['total_value_mode'] ?? 'automatic') === 'automatic') {
            $contract->total_value = $contract->installmentPreview()['planned_total'];
        }

        return $contract;
    }

    /**
     * Copy validated values to a model. Missing update fields intentionally
     * remain untouched so legacy PATCH requests keep their old shape.
     */
    public static function apply(Contract $contract, array $data): void
    {
        $fields = [
            'name', 'contract_number', 'contract_type', 'contract_type_id',
            'status_label_id', 'supplier_id', 'start_date', 'end_date',
            'billing_cycle', 'billing_day', 'installment_value', 'total_value',
            'total_value_mode', 'total_installments', 'readjustment_index',
            'readjustment_month', 'description', 'notes',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $contract->{$field} = $data[$field];
            }
        }

        if (array_key_exists('company_id', $data)) {
            $contract->company_id = Company::getIdForCurrentUser($data['company_id']);
        }
    }

    private static function normalize(array $input, bool $localized): array
    {
        $data = $input;
        $invalidMoneyFields = [];

        foreach (self::MONEY_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            try {
                if (! $localized && $data[$field] !== null && $data[$field] !== ''
                    && (! is_scalar($data[$field]) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) $data[$field]))) {
                    throw new InvalidArgumentException('API money must use canonical decimals.');
                }
                $data[$field] = ContractMoney::toDecimal($data[$field]);
            } catch (InvalidArgumentException) {
                $data[$field] = null;
                $invalidMoneyFields[] = $field;
            }
        }

        if (($data['total_value_mode'] ?? null) === '') {
            $data['total_value_mode'] = 'automatic';
        }

        return [$data, $invalidMoneyFields];
    }

    private static function rules(bool $creating, ?Contract $contract, array $data): array
    {
        $legacy = $contract && self::isLegacy($contract);
        $required = static function ($rule) use ($creating): array {
            $rules = is_string($rule) ? explode('|', $rule) : [$rule];

            return array_merge($creating ? ['required'] : ['sometimes', 'required'], $rules);
        };
        $typeRule = Rule::exists('contract_types', 'id')->whereNull('deleted_at');
        if ($creating || ! $contract || (array_key_exists('contract_type_id', $data) && (int) $data['contract_type_id'] !== (int) $contract->contract_type_id)) {
            $typeRule = Rule::exists('contract_types', 'id')
                ->whereNull('deleted_at')
                ->where('is_active', true);
        }

        return [
            'name' => $required('string|max:255'),
            'contract_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_type' => $required(Rule::in(['recurring', 'one_time'])),
            'contract_type_id' => $creating
                ? ['required', 'integer', $typeRule]
                : ['sometimes', $legacy ? 'nullable' : 'integer', $typeRule],
            'status_label_id' => $creating
                ? ['required', 'integer', Rule::exists('contract_status_labels', 'id')->where('scope', 'contract')->whereNull('deleted_at')]
                : ['sometimes', 'integer', Rule::exists('contract_status_labels', 'id')->where('scope', 'contract')->whereNull('deleted_at')],
            'supplier_id' => $creating
                ? ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')]
                : ['sometimes', $legacy ? 'nullable' : 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'company_id' => $creating
                ? ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')]
                : ['sometimes', $legacy ? 'nullable' : 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'start_date' => $required('date_format:Y-m-d'),
            'end_date' => $creating
                ? ['required', 'date_format:Y-m-d']
                : array_values(array_filter([
                    'sometimes',
                    $legacy ? 'nullable' : null,
                    'date_format:Y-m-d',
                ])),
            'billing_cycle' => $required(Rule::in(['monthly', 'quarterly', 'semiannual', 'annual', 'one_time'])),
            'billing_day' => $creating
                ? ['required', 'integer', 'between:1,31']
                : array_values(array_filter([
                    'sometimes',
                    $legacy ? 'nullable' : null,
                    'integer',
                    'between:1,31',
                ])),
            'installment_value' => $required('numeric|min:0'),
            'total_value_mode' => ['sometimes', 'in:automatic,manual'],
            'total_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'total_installments' => $creating
                ? ['required_if:contract_type,one_time', 'nullable', 'integer', 'min:1']
                : ['sometimes', 'nullable', 'integer', 'min:1'],
            'readjustment_index' => ['sometimes', 'nullable', 'string', 'max:50'],
            'readjustment_month' => ['sometimes', 'nullable', 'integer', 'between:1,12'],
            'description' => $creating
                ? ['required', 'string']
                : ($legacy ? ['sometimes', 'nullable', 'string'] : ['sometimes', 'required', 'string']),
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    private static function previewRules(array $data): array
    {
        return [
            'contract_type' => ['required', Rule::in(['recurring', 'one_time'])],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'semiannual', 'annual', 'one_time'])],
            'billing_day' => ['required', 'integer', 'between:1,31'],
            'installment_value' => ['required', 'numeric', 'min:0'],
            'total_value_mode' => ['sometimes', 'in:automatic,manual'],
            'total_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'total_installments' => ['required_if:contract_type,one_time', 'nullable', 'integer', 'min:1'],
        ];
    }

    private static function validateReferences($validator, array $data): void
    {
        if (array_key_exists('company_id', $data) && $data['company_id'] !== null && $data['company_id'] !== '') {
            $company = Company::find($data['company_id']);
            if (! $company || ! Company::isCurrentUserHasAccess($company)) {
                $validator->errors()->add('company_id', trans('admin/contracts/validation.company_unavailable'));
            }
        }

        if (array_key_exists('supplier_id', $data) && $data['supplier_id'] !== null && $data['supplier_id'] !== '') {
            if (! Supplier::whereKey($data['supplier_id'])->exists()) {
                $validator->errors()->add('supplier_id', trans('admin/contracts/validation.supplier_unavailable'));
            }
        }
    }

    private static function validateSchedule($validator, array $data, bool $creating, ?Contract $contract): void
    {
        if (! $creating && $contract && ! array_intersect(array_keys($data), [
            'contract_type', 'start_date', 'end_date', 'billing_cycle', 'billing_day',
            'installment_value', 'total_value', 'total_value_mode', 'total_installments',
        ])) {
            return;
        }
        $effective = self::effectiveData($data, $contract);
        if (! isset($effective['contract_type'], $effective['start_date'], $effective['billing_cycle'], $effective['billing_day'], $effective['installment_value'])) {
            return;
        }

        $start = self::parseDate($effective['start_date']);
        $end = self::parseDate($effective['end_date'] ?? null);
        if ($start && $end && $end->lt($start)) {
            $validator->errors()->add('end_date', trans('admin/contracts/validation.end_after_start'));
        }

        if (($effective['contract_type'] ?? null) === 'recurring'
            && ($effective['billing_cycle'] ?? null) === 'one_time') {
            $validator->errors()->add('billing_cycle', trans('admin/contracts/validation.incompatible_cycle'));
        }

        if (($effective['contract_type'] ?? null) === 'one_time'
            && ($effective['billing_cycle'] ?? null) === 'one_time'
            && (int) ($effective['total_installments'] ?? 1) > 1) {
            $validator->errors()->add('total_installments', trans('admin/contracts/validation.one_time_cycle'));
        }

        $draft = self::draftContract($effective);
        try {
            $draft->installmentPreview();
        } catch (InvalidArgumentException $exception) {
            $validator->errors()->add(
                $draft->contract_type === 'one_time' ? 'total_installments' : 'end_date',
                $exception->getMessage() === 'A one-time billing cycle supports only one installment.'
                    ? trans('admin/contracts/validation.one_time_cycle')
                    : trans('admin/contracts/validation.quantity_period')
            );
        }

        $mode = $effective['total_value_mode'] ?? 'automatic';
        $totalWasSubmitted = array_key_exists('total_value', $data);
        $modeWasSubmitted = array_key_exists('total_value_mode', $data);
        if ($mode === 'manual'
            && ($creating || $totalWasSubmitted || $modeWasSubmitted)
            && (! array_key_exists('total_value', $effective) || $effective['total_value'] === null)) {
            $validator->errors()->add('total_value', trans('admin/contracts/validation.manual_total_required'));
        }

        if (($effective['contract_type'] ?? null) === 'recurring'
            && array_key_exists('total_installments', $effective)
            && $effective['total_installments'] !== null
            && $effective['total_installments'] !== '') {
            $validator->errors()->add('total_installments', trans('admin/contracts/validation.recurring_quantity'));
        }
    }

    private static function effectiveData(array $data, ?Contract $contract): array
    {
        if (! $contract) {
            return array_merge(['total_value_mode' => 'automatic'], $data);
        }

        $existing = [
            'name' => $contract->name,
            'contract_type' => $contract->contract_type,
            'contract_type_id' => $contract->contract_type_id,
            'status_label_id' => $contract->status_label_id,
            'supplier_id' => $contract->supplier_id,
            'company_id' => $contract->company_id,
            'start_date' => $contract->start_date?->format('Y-m-d'),
            'end_date' => $contract->end_date?->format('Y-m-d'),
            'billing_cycle' => $contract->billing_cycle,
            'billing_day' => $contract->billing_day,
            'installment_value' => $contract->installment_value,
            'total_value' => $contract->total_value,
            'total_value_mode' => $contract->total_value_mode ?: 'automatic',
            'total_installments' => $contract->total_installments,
            'description' => $contract->description,
            'notes' => $contract->notes,
        ];

        return array_merge($existing, $data);
    }

    private static function draftContract(array $data): Contract
    {
        $contract = new Contract;
        foreach ([
            'contract_type', 'start_date', 'end_date', 'billing_cycle', 'billing_day',
            'installment_value', 'total_value', 'total_value_mode', 'total_installments',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $contract->{$field} = $data[$field];
            }
        }

        return $contract;
    }

    private static function isLegacy(Contract $contract): bool
    {
        return $contract->supplier_id === null
            || $contract->company_id === null
            || $contract->end_date === null
            || $contract->billing_day === null
            || $contract->description === null
            || $contract->contract_type_id === null;
    }

    private static function shouldRefreshAutomaticTotal(array $data, bool $creating): bool
    {
        if ($creating) {
            return true;
        }

        return (bool) array_intersect(array_keys($data), [
            'contract_type',
            'start_date',
            'end_date',
            'billing_cycle',
            'billing_day',
            'installment_value',
            'total_installments',
            'total_value',
            'total_value_mode',
        ]);
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function messages(): array
    {
        return [
            'money.invalid' => trans('admin/contracts/validation.money_invalid'),
            'after_or_equal' => trans('admin/contracts/validation.end_after_start'),
            'billing_day.between' => trans('admin/contracts/validation.between'),
            'billing_day.integer' => trans('admin/contracts/validation.integer'),
            'total_installments.integer' => trans('admin/contracts/validation.integer'),
            'total_installments.min' => trans('admin/contracts/validation.min'),
            'total_installments.required_if' => trans('admin/contracts/validation.required_if'),
            'installment_value.numeric' => trans('admin/contracts/validation.numeric'),
            'installment_value.min' => trans('admin/contracts/validation.min'),
            'total_value.numeric' => trans('admin/contracts/validation.numeric'),
            'total_value.min' => trans('admin/contracts/validation.min'),
            'start_date.date_format' => trans('admin/contracts/validation.date_format'),
            'end_date.date_format' => trans('admin/contracts/validation.date_format'),
        ];
    }

    private static function attributes(): array
    {
        return [
            'contract_type_id' => trans('admin/contracts/general.contract_classification'),
            'status_label_id' => trans('admin/contracts/general.status_label'),
            'supplier_id' => trans('general.supplier'),
            'company_id' => trans('general.company'),
            'start_date' => trans('admin/contracts/general.start_date'),
            'end_date' => trans('admin/contracts/general.end_date'),
            'billing_cycle' => trans('admin/contracts/general.billing_cycle'),
            'billing_day' => trans('admin/contracts/general.billing_day'),
            'installment_value' => trans('admin/contracts/general.installment_value'),
            'total_value' => trans('admin/contracts/general.total_value'),
            'total_installments' => trans('admin/contracts/general.total_installments'),
            'description' => trans('admin/contracts/general.description'),
        ];
    }
}

<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\ContractInstallment;
use App\Models\ContractStatusLabel;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Builds the server-side amendment preview used by both web and API flows.
 *
 * The preview token binds the submitted intent to the contract/installment
 * snapshot observed by the server. The mutation still runs under the
 * contract lock and revalidates the request before applying effects.
 */
class ContractAmendmentPreviewService
{
    public const TYPES = [
        'readjustment',
        'scope_change',
        'renewal',
        'termination',
    ];

    public function makeValidator(array $input, Contract $contract): ValidatorContract
    {
        $moneyRules = [
            'nullable',
            'numeric',
            'min:0',
            'regex:/^\d+(?:\.\d{1,2})?$/',
        ];

        $rules = [
            'amendment_type'   => ['required', 'in:'.implode(',', self::TYPES)],
            'description'      => ['required', 'string'],
            'rectifies_amendment_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('contract_amendments', 'id')->where('contract_id', $contract->id)->whereNull('deleted_at')],
            // Approved v1 rule: confirmation applies effects immediately,
            // regardless of effective date; this is not a scheduler.
            'effective_date'   => ['required', 'date_format:Y-m-d'],
            'old_value'        => $moneyRules,
            'new_value'        => $moneyRules,
            'old_end_date'     => ['nullable', 'date_format:Y-m-d'],
            'new_end_date'     => ['nullable', 'date_format:Y-m-d'],
            'ticket_reference' => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string'],
        ];

        $type = $input['amendment_type'] ?? null;

        if ($type === 'readjustment') {
            $rules['new_value'] = [
                'required',
                'numeric',
                'min:0.01',
                'regex:/^\d+(?:\.\d{1,2})?$/',
            ];
        }

        if ($type === 'renewal') {
            $rules['old_end_date'] = ['required', 'date_format:Y-m-d'];
            $rules['new_end_date'] = ['required', 'date_format:Y-m-d', 'after:old_end_date'];
        }

        $attributes = [
            'amendment_type' => trans('admin/contracts/general.amendment_type'),
            'description' => trans('admin/contracts/general.description'),
            'effective_date' => trans('admin/contracts/general.effective_date'),
            'old_value' => trans('admin/contracts/general.old_value'),
            'new_value' => trans('admin/contracts/general.new_value'),
            'old_end_date' => trans('admin/contracts/general.old_end_date'),
            'new_end_date' => trans('admin/contracts/general.new_end_date'),
            'ticket_reference' => trans('admin/contracts/general.ticket_reference'),
            'notes' => trans('general.notes'),
        ];

        $messages = [
            'amendment_type.required' => trans('admin/contracts/message.amendment.validation.type_required'),
            'amendment_type.in' => trans('admin/contracts/message.amendment.validation.type_invalid'),
            'description.required' => trans('admin/contracts/message.amendment.validation.description_required'),
            'effective_date.required' => trans('admin/contracts/message.amendment.validation.date_required'),
            'effective_date.date_format' => trans('admin/contracts/message.amendment.validation.date_invalid'),
            '*.date_format' => trans('admin/contracts/message.amendment.validation.date_invalid'),
            'old_value.numeric' => trans('admin/contracts/message.amendment.validation.value_invalid'),
            'old_value.min' => trans('admin/contracts/message.amendment.validation.value_invalid'),
            'old_value.regex' => trans('admin/contracts/message.amendment.validation.value_invalid'),
            'new_value.required' => trans('admin/contracts/message.amendment.validation.new_value_required'),
            'new_value.numeric' => trans('admin/contracts/message.amendment.validation.value_invalid'),
            'new_value.min' => trans('admin/contracts/message.amendment.validation.new_value_invalid'),
            'new_value.regex' => trans('admin/contracts/message.amendment.validation.value_invalid'),
            'old_end_date.required' => trans('admin/contracts/message.amendment.validation.old_end_required'),
            'new_end_date.required' => trans('admin/contracts/message.amendment.validation.new_end_required'),
            'new_end_date.after' => trans('admin/contracts/message.amendment.validation.new_end_after_old'),
            'ticket_reference.max' => trans('admin/contracts/message.amendment.validation.ticket_too_long'),
        ];

        $validator = Validator::make($input, $rules, $messages, $attributes);

        $validator->after(function (ValidatorContract $validator) use ($input, $contract, $type): void {
            if ($type === 'readjustment' && $this->hasInputValue($input, 'old_value')) {
                try {
                    if ($this->moneyToCents($input['old_value']) !== $this->moneyToCents($this->currentInstallmentValue($contract))) {
                        $validator->errors()->add(
                            'old_value',
                            trans('admin/contracts/message.amendment.validation.old_value_mismatch')
                        );
                    }
                } catch (Throwable) {
                    // The field-level rules report malformed values.
                }
            }

            if ($type === 'renewal' && $this->isDateString($input['old_end_date'] ?? null)) {
                $currentEnd = $contract->end_date?->format('Y-m-d');
                if ($currentEnd === null || $input['old_end_date'] !== $currentEnd) {
                    $validator->errors()->add(
                        'old_end_date',
                        trans('admin/contracts/message.amendment.validation.old_end_mismatch')
                    );
                }
            }
        });

        return $validator;
    }

    /**
     * Normalize only validated input and replace client snapshots with the
     * values observed on the server.
     */
    public function normalizeValidated(array $validated, Contract $contract): array
    {
        $type = $validated['amendment_type'];

        $normalized = [
            'amendment_type' => $type,
            'description' => $validated['description'],
            'rectifies_amendment_id' => isset($validated['rectifies_amendment_id']) ? (int) $validated['rectifies_amendment_id'] : null,
            'effective_date' => $this->dateString($validated['effective_date']),
            'ticket_reference' => $validated['ticket_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'old_value' => null,
            'new_value' => null,
            'old_end_date' => null,
            'new_end_date' => null,
        ];

        if ($type === 'readjustment') {
            // old_value is an audit snapshot, never a client assertion.
            $normalized['old_value'] = $this->centsToDecimal(
                $this->moneyToCents($this->currentInstallmentValue($contract))
            );
            $normalized['new_value'] = $this->centsToDecimal(
                $this->moneyToCents($validated['new_value'])
            );
        }

        if ($type === 'renewal') {
            $normalized['old_end_date'] = $contract->end_date?->format('Y-m-d');
            $normalized['new_end_date'] = $this->dateString($validated['new_end_date']);

            // Preserve the pre-existing optional API behavior where a
            // renewal can carry a new installment value.
            if ($this->hasInputValue($validated, 'new_value')) {
                $normalized['old_value'] = $this->centsToDecimal(
                    $this->moneyToCents($this->currentInstallmentValue($contract))
                );
                $normalized['new_value'] = $this->centsToDecimal(
                    $this->moneyToCents($validated['new_value'])
                );
            }
        }

        return $normalized;
    }

    /**
     * Validate and normalize input for non-controller callers.
     */
    public function normalized(array $input, Contract $contract): array
    {
        $validator = $this->makeValidator($input, $contract);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $this->normalizeValidated($validator->validated(), $contract);
    }

    public function build(array $input, Contract $contract): array
    {
        $normalized = $this->normalized($input, $contract);
        $type = $normalized['amendment_type'];
        $token = $this->issueToken($contract, $normalized);

        $preview = [
            'type' => $type,
            'preview_valid' => true,
            'rectifies_amendment_id' => $normalized['rectifies_amendment_id'] ?? null,
            'has_side_effects' => $type !== 'scope_change',
            'preview_token' => $token,
            'effective_date' => $normalized['effective_date'],
        ];

        if ($type === 'readjustment') {
            return array_merge($preview, $this->readjustmentPreview($normalized, $contract));
        }

        if ($type === 'renewal') {
            return array_merge($preview, $this->renewalPreview($normalized, $contract));
        }

        if ($type === 'termination') {
            return array_merge($preview, $this->terminationPreview($normalized, $contract));
        }

        return array_merge($preview, [
            'message' => trans('admin/contracts/message.amendment.no_side_effects'),
            'impact' => [
                'description' => $normalized['description'],
            'rectifies_amendment_id' => $normalized['rectifies_amendment_id'] ?? null,
                'effective_date' => $normalized['effective_date'],
                'documentary_only' => true,
                'financial_effect' => 'none',
            ],
            'details' => [
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.description'),
                    $normalized['description']
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.effective_date'),
                    $this->dateDisplay($normalized['effective_date'])
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.financial_effect'),
                    trans('admin/contracts/message.amendment.preview_details.documentary_only')
                ),
            ],
        ]);
    }

    /**
     * Return errors when a preview token is missing, forged, for another
     * intent, or no longer matches the locked contract snapshot.
     */
    public function previewTokenErrors(
        ?string $token,
        Contract $contract,
        array $normalized,
        bool $required = false
    ): array {
        if (! $token) {
            return $required
                ? ['preview_token' => [trans('admin/contracts/message.amendment.validation.preview_required')]]
                : [];
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['preview_token' => [trans('admin/contracts/message.amendment.validation.preview_invalid')]];
        }

        $sameContract = (int) ($payload['contract_id'] ?? 0) === (int) $contract->id;
        $sameType = ($payload['amendment_type'] ?? null) === $normalized['amendment_type'];
        $sameUser = (int) ($payload['user_id'] ?? 0) === (int) auth()->id();
        $sameIntent = isset($payload['intent_hash'])
            && hash_equals((string) $payload['intent_hash'], $this->intentHash($normalized));
        $sameSnapshot = isset($payload['snapshot_hash'])
            && hash_equals((string) $payload['snapshot_hash'], $this->snapshotHash($contract));

        if (! $sameContract || ! $sameType || ! $sameUser || ! $sameIntent || ! $sameSnapshot) {
            return ['preview_token' => [trans('admin/contracts/message.amendment.validation.preview_stale')]];
        }

        return [];
    }

    public function currentInstallmentValue(Contract $contract): mixed
    {
        return $contract->getRawOriginal('installment_value') ?? $contract->installment_value;
    }

    private function readjustmentPreview(array $input, Contract $contract): array
    {
        $rows = $this->installmentRows($contract);
        $effectiveDate = $this->parseDate($input['effective_date']);
        $affected = $rows->filter(fn (ContractInstallment $row): bool =>
            $this->statusType($row) === 'pending'
            && $row->due_date
            && $row->due_date->gte($effectiveDate)
        )->values();
        $preserved = $rows->reject(fn (ContractInstallment $row): bool => $affected->contains('id', $row->id))->values();

        $oldCents = $this->moneyToCents($input['old_value']);
        $newCents = $this->moneyToCents($input['new_value']);
        $deltaCents = $newCents - $oldCents;
        $affectedBefore = $this->sumCents($affected);
        $affectedAfter = $affected->count() * $newCents;
        $preservedValue = $this->sumCents($preserved);
        $affectedSummary = $this->installmentSummary($affected, $affectedAfter);
        $affectedSummary['value_before'] = $this->centsToDecimal($affectedBefore);

        return [
            'old_value' => $input['old_value'],
            'new_value' => $input['new_value'],
            'pending_affected' => $affected->count(),
            'overdue_unchanged' => $rows->filter(fn (ContractInstallment $row): bool => $this->statusType($row) === 'overdue')->count(),
            'message' => trans('admin/contracts/message.amendment.readjustment.preview', [
                'count' => $affected->count(),
                'old' => $this->displayMoney($oldCents),
                'new' => $this->displayMoney($newCents),
            ]),
            'impact' => [
                'current_value' => $input['old_value'],
                'new_value' => $input['new_value'],
                'difference' => $this->centsToDecimal($deltaCents),
                'difference_absolute' => $this->centsToDecimal(abs($deltaCents)),
                'difference_percent' => $this->percentage($deltaCents, $oldCents),
                'effective_date' => $input['effective_date'],
                'affected_installments' => $affectedSummary,
                'preserved_installments' => $this->installmentSummary($preserved, $preservedValue),
            ],
            'details' => [
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.current_value'),
                    $this->displayMoney($oldCents)
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.new_value'),
                    $this->displayMoney($newCents)
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.difference_absolute'),
                    $this->displayMoney(abs($deltaCents))
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.difference_percent'),
                    $this->percentageDisplay($this->percentage($deltaCents, $oldCents))
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.effective_date'),
                    $this->dateDisplay($input['effective_date'])
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.affected_installments'),
                    $this->installmentDisplay($affectedSummary, true)
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.preserved_installments'),
                    $this->installmentDisplay($this->installmentSummary($preserved, $preservedValue))
                ),
            ],
        ];
    }

    private function renewalPreview(array $input, Contract $contract): array
    {
        $oldEnd = $this->parseDate($input['old_end_date']);
        $newEnd = $this->parseDate($input['new_end_date']);
        $dates = $contract->recurringInstallmentDates($oldEnd->copy()->addDay(), $newEnd);
        $rows = $this->installmentRows($contract);
        $currentValueCents = $this->moneyToCents($this->currentInstallmentValue($contract));
        $renewalValueCents = $input['new_value'] !== null
            ? $this->moneyToCents($input['new_value'])
            : $currentValueCents;
        $generatedValueCents = count($dates) * $renewalValueCents;
        $preservedSummary = $this->installmentSummary($rows, $this->sumCents($rows));

        return [
            'estimated_count' => count($dates),
            'new_end_date' => $input['new_end_date'],
            'message' => trans('admin/contracts/message.amendment.renewal.preview', [
                'count' => count($dates),
                'date' => $newEnd->format('d/m/Y'),
            ]),
            'impact' => [
                'current_end_date' => $input['old_end_date'],
                'new_end_date' => $input['new_end_date'],
                'period_added_days' => $oldEnd->diffInDays($newEnd),
                'current_value' => $this->centsToDecimal($currentValueCents),
                'new_value' => $input['new_value'],
                'generated_installments' => [
                    'count' => count($dates),
                    'value' => $this->centsToDecimal($generatedValueCents),
                    'dates' => array_map(fn (Carbon $date): string => $date->format('Y-m-d'), $dates),
                ],
                'preserved_installments' => $preservedSummary,
            ],
            'details' => [
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.current_end_date'),
                    $oldEnd->format('d/m/Y')
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.new_end_date'),
                    $newEnd->format('d/m/Y')
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.period_added'),
                    trans('admin/contracts/message.amendment.preview_details.days', [
                        'count' => $oldEnd->diffInDays($newEnd),
                    ])
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.generated_installments'),
                    $this->datesDisplay($dates, $generatedValueCents)
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.preserved_installments'),
                    $this->installmentDisplay($preservedSummary)
                ),
            ],
        ];
    }

    private function terminationPreview(array $input, Contract $contract): array
    {
        $rows = $this->installmentRows($contract);
        $effectiveDate = $this->parseDate($input['effective_date']);
        $cancelled = $rows->filter(fn (ContractInstallment $row): bool =>
            $this->statusType($row) === 'pending'
            && $row->due_date
            && $row->due_date->gt($effectiveDate)
        )->values();
        $preserved = $rows->reject(fn (ContractInstallment $row): bool => $cancelled->contains('id', $row->id))->values();
        $cancelledSummary = $this->installmentSummary($cancelled, $this->sumCents($cancelled));
        $preservedSummary = $this->installmentSummary($preserved, $this->sumCents($preserved));
        $status = ContractStatusLabel::defaultForMetaType('contract', 'cancelled');

        return [
            'pending_cancel' => $cancelled->count(),
            'overdue_unchanged' => $rows->filter(fn (ContractInstallment $row): bool => $this->statusType($row) === 'overdue')->count(),
            'paid_unchanged' => $rows->filter(fn (ContractInstallment $row): bool => $this->statusType($row) === 'paid')->count(),
            'message' => trans('admin/contracts/message.amendment.termination.preview', [
                'count' => $cancelled->count(),
                'date' => $input['effective_date'],
            ]),
            'impact' => [
                'result_status' => [
                    'meta_type' => 'cancelled',
                    'name' => $status?->name ?? trans('admin/contracts/message.amendment.preview_details.cancelled_status'),
                ],
                'effective_date' => $input['effective_date'],
                'cancelled_installments' => $cancelledSummary,
                'preserved_installments' => $preservedSummary,
                'preserved_breakdown' => $this->statusBreakdown($preserved),
                'financial_policy' => 'no_automatic_fine_refund_proration',
            ],
            'details' => [
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.result_status'),
                    $status?->name ?? trans('admin/contracts/message.amendment.preview_details.cancelled_status')
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.effective_date'),
                    $this->dateDisplay($input['effective_date'])
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.cancelled_installments'),
                    $this->installmentDisplay($cancelledSummary)
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.preserved_installments'),
                    $this->installmentDisplay($preservedSummary)
                ),
                $this->detail(
                    trans('admin/contracts/message.amendment.preview_details.financial_policy'),
                    trans('admin/contracts/message.amendment.preview_details.termination_policy')
                ),
            ],
        ];
    }

    private function installmentRows(Contract $contract): Collection
    {
        return $contract->installments()
            ->with('statusLabel')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    private function installmentSummary(Collection $rows, ?int $valueCents = null): array
    {
        $valueCents ??= $this->sumCents($rows);

        return [
            'count' => $rows->count(),
            'value' => $this->centsToDecimal($valueCents),
            'dates' => $rows->map(fn (ContractInstallment $row): string => $row->due_date?->format('Y-m-d'))->filter()->values()->all(),
        ];
    }

    private function statusBreakdown(Collection $rows): array
    {
        return $rows->groupBy(fn (ContractInstallment $row): string => $this->statusType($row))
            ->map(fn (Collection $group): array => $this->installmentSummary($group))
            ->all();
    }

    private function statusType(ContractInstallment $row): string
    {
        return $row->statusLabel?->meta_type ?? 'unknown';
    }

    private function sumCents(Collection $rows): int
    {
        return $rows->reduce(function (int $carry, ContractInstallment $row): int {
            $raw = $row->getRawOriginal('expected_value') ?? $row->expected_value ?? '0.00';

            return $carry + $this->moneyToCents($raw);
        }, 0);
    }

    private function issueToken(Contract $contract, array $normalized): string
    {
        return Crypt::encryptString(json_encode([
            'version' => 1,
            'contract_id' => (int) $contract->id,
            'amendment_type' => $normalized['amendment_type'],
            'user_id' => (int) auth()->id(),
            'snapshot_hash' => $this->snapshotHash($contract),
            'intent_hash' => $this->intentHash($normalized),
        ], JSON_THROW_ON_ERROR));
    }

    private function snapshotHash(Contract $contract): string
    {
        $installments = $contract->installments()
            ->withTrashed()
            ->orderBy('id')
            ->get([
                'id',
                'status_label_id',
                'due_date',
                'expected_value',
                'paid_value',
                'payment_date',
                'updated_at',
                'deleted_at',
            ])
            ->map(fn (ContractInstallment $row): array => [
                'id' => (int) $row->id,
                'status_label_id' => (int) $row->status_label_id,
                'due_date' => $row->getRawOriginal('due_date'),
                'expected_value' => $row->getRawOriginal('expected_value'),
                'paid_value' => $row->getRawOriginal('paid_value'),
                'payment_date' => $row->getRawOriginal('payment_date'),
                'updated_at' => $row->getRawOriginal('updated_at'),
                'deleted_at' => $row->getRawOriginal('deleted_at'),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'contract' => [
                'id' => (int) $contract->id,
                'updated_at' => $contract->getRawOriginal('updated_at'),
                'status_label_id' => (int) $contract->status_label_id,
                'start_date' => $contract->getRawOriginal('start_date'),
                'billing_day' => $contract->getRawOriginal('billing_day'),
                'billing_cycle' => $contract->getRawOriginal('billing_cycle'),
                'end_date' => $contract->getRawOriginal('end_date'),
                'installment_value' => $contract->getRawOriginal('installment_value'),
            ],
            'installments' => $installments,
        ], JSON_THROW_ON_ERROR));
    }

    private function intentHash(array $normalized): string
    {
        return hash('sha256', json_encode([
            'amendment_type' => $normalized['amendment_type'],
            'description' => $normalized['description'],
            'rectifies_amendment_id' => $normalized['rectifies_amendment_id'] ?? null,
            'effective_date' => $normalized['effective_date'],
            'old_value' => $normalized['old_value'],
            'new_value' => $normalized['new_value'],
            'old_end_date' => $normalized['old_end_date'],
            'new_end_date' => $normalized['new_end_date'],
            'ticket_reference' => $normalized['ticket_reference'],
            'notes' => $normalized['notes'],
        ], JSON_THROW_ON_ERROR));
    }

    private function detail(string $label, string $value): array
    {
        return ['label' => $label, 'value' => $value];
    }

    private function installmentDisplay(array $summary, bool $includeAfter = false): string
    {
        $value = $this->displayMoney($this->moneyToCents($summary['value']));
        $text = trans('admin/contracts/message.amendment.preview_details.installments_summary', [
            'count' => $summary['count'],
            'value' => $value,
        ]);

        if ($includeAfter && isset($summary['value_before'])) {
            $before = $this->displayMoney($this->moneyToCents($summary['value_before']));
            $text .= ' '.trans('admin/contracts/message.amendment.preview_details.from_to', [
                'from' => $before,
                'to' => $value,
            ]);
        }

        return $text.' — '.$this->datesDisplayFromStrings($summary['dates']);
    }

    private function datesDisplay(array $dates, int $valueCents): string
    {
        $formatted = array_map(fn (Carbon $date): string => $date->format('d/m/Y'), $dates);
        $dateText = count($formatted) > 0
            ? implode(', ', $formatted)
            : trans('admin/contracts/message.amendment.preview_details.none');

        return trans('admin/contracts/message.amendment.preview_details.installments_summary', [
            'count' => count($dates),
            'value' => $this->displayMoney($valueCents),
        ]).' — '.$dateText;
    }

    private function datesDisplayFromStrings(array $dates): string
    {
        if (count($dates) === 0) {
            return trans('admin/contracts/message.amendment.preview_details.none');
        }

        return implode(', ', array_map(
            fn (string $date): string => $this->dateDisplay($date),
            $dates
        ));
    }

    private function displayMoney(int $cents): string
    {
        if (app()->getLocale() === 'pt-BR') {
            return 'R$ '.number_format($cents / 100, 2, ',', '.');
        }

        return '$ '.Helper::formatCurrencyOutput($this->centsToDecimal($cents));
    }

    private function percentageDisplay(?string $percentage): string
    {
        return $percentage === null
            ? trans('admin/contracts/message.amendment.preview_details.not_calculated')
            : $percentage.'%';
    }

    private function percentage(int $deltaCents, int $baseCents): ?string
    {
        if ($baseCents === 0) {
            return null;
        }

        $absoluteBase = abs($baseCents);
        $scaled = intdiv(abs($deltaCents) * 10000 + intdiv($absoluteBase, 2), $absoluteBase);
        $whole = intdiv($scaled, 100);
        $fraction = str_pad((string) ($scaled % 100), 2, '0', STR_PAD_LEFT);
        $sign = $deltaCents < 0 ? '-' : '';

        return $sign.$whole.'.'.$fraction;
    }

    private function moneyToCents(mixed $value): int
    {
        $string = trim((string) $value);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $string, $matches)) {
            throw new \InvalidArgumentException('Invalid decimal money value.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function centsToDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function dateString(string $date): string
    {
        return $this->parseDate($date)->format('Y-m-d');
    }

    private function parseDate(string $date): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $date);
    }

    private function dateDisplay(string $date): string
    {
        return $this->parseDate($date)->format('d/m/Y');
    }

    private function isDateString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            $this->parseDate($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasInputValue(array $input, string $key): bool
    {
        return array_key_exists($key, $input)
            && $input[$key] !== null
            && trim((string) $input[$key]) !== '';
    }
}

<?php

namespace App\Models;

use App\Http\Traits\UniqueUndeletedTrait;
use App\Models\Traits\CompanyableTrait;
use App\Models\Traits\HasUploads;
use App\Models\Traits\Loggable;
use App\Models\Traits\Searchable;
use App\Presenters\ContractPresenter;
use App\Presenters\Presentable;
use App\Services\ContractMoney;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Watson\Validating\ValidatingTrait;

class Contract extends SnipeModel
{
    use CompanyableTrait;
    use HasFactory;
    use HasUploads;
    use Loggable;
    use Presentable;
    use Searchable;
    use SoftDeletes;
    use UniqueUndeletedTrait;
    use ValidatingTrait;

    protected $table = 'contracts';
    protected $presenter = ContractPresenter::class;
    protected $injectUniqueIdentifier = true;

    protected $fillable = [
        'name',
        'contract_number',
        'contract_type',
        'contract_type_id',
        'status_label_id',
        'supplier_id',
        'company_id',
        'start_date',
        'end_date',
        'billing_cycle',
        'billing_day',
        'installment_value',
        'total_value',
        'total_value_mode',
        'total_installments',
        'readjustment_index',
        'readjustment_month',
        'description',
        'notes',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'installment_value'  => 'decimal:2',
        'total_value'        => 'decimal:2',
        'total_value_mode'   => 'string',
        'total_installments' => 'integer',
        'readjustment_month' => 'integer',
        'billing_day'        => 'integer',
        'supplier_id'        => 'integer',
        'company_id'         => 'integer',
        'status_label_id'    => 'integer',
        'contract_type_id'   => 'integer',
    ];

    protected $rules = [
        'name'               => 'required|max:255|string',
        'contract_number'    => 'nullable|max:100|unique_undeleted',
        'contract_type'      => 'required|in:recurring,one_time',
        'status_label_id'    => 'required|exists:contract_status_labels,id',
        'supplier_id'        => 'nullable|exists:suppliers,id',
        'start_date'         => 'required|date',
        'end_date'           => 'nullable|date|after_or_equal:start_date',
        'billing_cycle'      => 'nullable|in:monthly,quarterly,semiannual,annual,one_time',
        'billing_day'        => 'nullable|integer|min:1|max:31',
        'installment_value'  => 'required|numeric|min:0',
        'total_value'        => 'nullable|numeric|min:0',
        'total_value_mode'   => 'nullable|in:automatic,manual',
        'contract_type_id'   => 'nullable|exists:contract_types,id',
        'total_installments' => 'nullable|integer|min:1',
        'readjustment_index' => 'nullable|max:50|string',
        'readjustment_month' => 'nullable|integer|min:1|max:12',
        'description'        => 'nullable|string',
        'notes'              => 'nullable|string',
    ];

    protected $searchableAttributes = ['name', 'contract_number', 'description', 'notes'];

    protected $searchableRelations = [
        'supplier' => ['name'],
        'company'  => ['name'],
    ];

    // ── Relationships ───────────────────────────────────────────────

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function adminuser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel()
    {
        return $this->belongsTo(ContractStatusLabel::class, 'status_label_id');
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id')->withTrashed();
    }

    public function installments()
    {
        return $this->hasMany(ContractInstallment::class, 'contract_id');
    }

    public function amendments()
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id');
    }

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'contract_asset')
            ->withPivot('created_at');
    }

    // ── Scopes by meta_type ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('contract', 'active'));
    }

    public function scopeDraft($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('contract', 'draft'));
    }

    public function scopeSuspended($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('contract', 'suspended'));
    }

    public function scopeCancelled($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('contract', 'cancelled'));
    }

    public function scopeExpired($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('contract', 'expired'));
    }

    /**
     * Scope: contratos com end_date dentro de $days dias a partir de hoje.
     * Exclui contratos com end_date null (vigência indeterminada).
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('end_date')
            ->where('end_date', '>=', now()->startOfDay())
            ->where('end_date', '<=', now()->addDays($days)->endOfDay());
    }

    // ── Installment generation ──────────────────────────────────────

    public const BILLING_CYCLES = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannual' => 6,
        'annual' => 12,
        'one_time' => 0,
    ];

    public function billingCycleIntervalInMonths(): int
    {
        return self::BILLING_CYCLES[$this->billing_cycle] ?? 1;
    }

    /**
     * Return the dates that a new contract would plan, without considering
     * installments already stored for an existing contract.
     */
    public function plannedInstallmentDates(): array
    {
        if ($this->contract_type === 'one_time') {
            return $this->oneTimeInstallmentDates();
        }

        return $this->recurringInstallmentDatesForPlan();
    }

    /**
     * Return the server-side preview used by the form and the preview API.
     * All totals are calculated in integer cents.
     */
    public function installmentPreview(): array
    {
        $dates = $this->plannedInstallmentDates();
        $installmentCents = ContractMoney::toCents($this->installment_value) ?? 0;
        $plannedTotalCents = count($dates) * $installmentCents;
        $negotiatedTotalCents = ContractMoney::toCents($this->total_value);

        return [
            'installments_count' => count($dates),
            'first_due_date' => ($dates[0] ?? null)?->toDateString(),
            'last_due_date' => ($dates[count($dates) - 1] ?? null)?->toDateString(),
            'planned_total' => ContractMoney::centsToDecimal($plannedTotalCents),
            'negotiated_total' => $negotiatedTotalCents === null
                ? null
                : ContractMoney::centsToDecimal($negotiatedTotalCents),
            'difference' => $negotiatedTotalCents === null
                ? null
                : ContractMoney::centsToDecimal($negotiatedTotalCents - $plannedTotalCents),
            'total_value_mode' => $this->total_value_mode ?: 'automatic',
            'dates' => array_map(static fn (Carbon $date) => $date->toDateString(), $dates),
        ];
    }

    /**
     * Generate installments based on contract type and billing cycle.
     */
    public function generateInstallments(?Carbon $from = null): int
    {
        // Serialize generation for the same persisted contract. Checking for
        // existing installments outside this lock permits concurrent duplicates.
        return $this->getConnection()->transaction(function () use ($from) {
            $contract = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            return $contract->generateInstallmentsLocked($from);
        });
    }

    protected function generateInstallmentsLocked(?Carbon $from): int
    {
        // The controller may have loaded the contract before a concurrent cancellation.
        if (in_array($this->statusLabel?->meta_type, ['expired', 'cancelled'], true)) {
            return 0;
        }

        $defaultStatus = ContractStatusLabel::defaultForMetaType('installment', 'pending');
        if (! $defaultStatus) {
            return 0;
        }

        $count = 0;

        if ($this->contract_type === 'one_time') {
            if ($this->installments()->withTrashed()->exists()) {
                return 0;
            }

            return $this->generateOneTimeInstallments($defaultStatus, $count);
        }

        return $this->generateRecurringInstallments($defaultStatus, $from, $count);
    }

    protected function resolveInstallmentDueDate(Carbon $baseDate): Carbon
    {
        $dueDate = $baseDate->copy();

        if ($this->billing_day) {
            $adjustedDay = min($this->billing_day, $dueDate->daysInMonth);
            $dueDate->day($adjustedDay);
        }

        return $dueDate;
    }

    protected function generateOneTimeInstallments(ContractStatusLabel $defaultStatus, int $count): int
    {
        $dates = $this->oneTimeInstallmentDates();
        $valuePerInstallment = ContractMoney::toDecimal($this->installment_value) ?? '0.00';

        foreach ($dates as $index => $dueDate) {
            $i = $index + 1;

            $installment = $this->installments()->create([
                'installment_number' => $i,
                'reference_date'     => $dueDate->copy()->startOfMonth(),
                'due_date'           => $dueDate,
                'expected_value'     => $valuePerInstallment,
                'status_label_id'    => $defaultStatus->id,
                'created_by'         => auth()->id(),
            ]);
            if (! $installment->exists) {
                throw new \RuntimeException('Installment creation failed');
            }
            $count++;
        }

        return $count;
    }

    protected function generateRecurringInstallments(ContractStatusLabel $defaultStatus, ?Carbon $from, int $count): int
    {
        $number = (int) ($this->installments()->withTrashed()->max('installment_number') ?: 0);
        foreach ($this->recurringInstallmentDates($from) as $dueDate) {
            $installment = $this->installments()->create([
                'installment_number' => ++$number,
                'reference_date' => $dueDate->copy()->startOfMonth(),
                'due_date' => $dueDate,
                'expected_value' => $this->installment_value,
                'status_label_id' => $defaultStatus->id,
                'created_by' => auth()->id(),
            ]);
            if (! $installment->exists) {
                throw new \RuntimeException('Installment creation failed');
            }
            $count++;
        }

        return $count;
    }

    /** Shared calendar for generation and renewal preview; from is only a lower bound. */
    public function recurringInstallmentDates(?Carbon $from = null, ?Carbon $until = null): array
    {
        return $this->recurringInstallmentDatesForPlan($from, $until, true);
    }

    protected function recurringInstallmentDatesForPlan(
        ?Carbon $from = null,
        ?Carbon $until = null,
        bool $excludeExisting = false
    ): array
    {
        if (! $this->start_date) {
            return [];
        }

        $start = $this->start_date->copy();
        $end = $until ?: $this->end_date;

        if (! $end) {
            // Contratos sem data final: gerar 12 meses a partir do início
            $end = ($from ?: $start)->copy()->addMonthsNoOverflow(11)->endOfMonth();
        }

        $monthsInterval = $this->billingCycleIntervalInMonths() ?: 1;

        $current = $this->billing_day ? $start->copy()->startOfMonth() : $start->copy();
        $anchor = $current->copy();
        $monthOffset = 0;
        $dates = [];

        $thresholdDate = $from ? $from->copy()->subDay() : null;

        if ($excludeExisting) {
            $lastInstallment = $this->installments()->withTrashed()->latest('due_date')->first();

            if ($lastInstallment?->due_date && (! $thresholdDate || $lastInstallment->due_date->gt($thresholdDate))) {
                $thresholdDate = $lastInstallment->due_date->copy();
            }
        }

        // A billing day earlier than the start day belongs to the next cycle.
        $startThreshold = $start->copy()->subDay();
        if (! $thresholdDate || $startThreshold->gt($thresholdDate)) {
            $thresholdDate = $startThreshold;
        }

        while ($thresholdDate && $this->resolveInstallmentDueDate($current)->lte($thresholdDate)) {
            $monthOffset += $monthsInterval;
            $current = $anchor->copy()->addMonthsNoOverflow($monthOffset);
        }

        while ($this->resolveInstallmentDueDate($current)->lte($end)) {
            $dueDate = $this->resolveInstallmentDueDate($current);
            $dates[] = $dueDate;
            $monthOffset += $monthsInterval;
            $current = $anchor->copy()->addMonthsNoOverflow($monthOffset);
        }

        return $dates;
    }

    protected function oneTimeInstallmentDates(): array
    {
        if (! $this->start_date) {
            return [];
        }

        $totalInstallments = (int) ($this->total_installments ?: 1);
        $interval = $this->billingCycleIntervalInMonths();

        if ($interval === 0 && $totalInstallments > 1) {
            throw new \InvalidArgumentException(
                'A one-time billing cycle supports only one installment.'
            );
        }

        $start = $this->start_date->copy();
        $end = $this->end_date;
        $anchor = $this->billing_day ? $start->copy()->startOfMonth() : $start->copy();
        $offset = 0;
        $firstDate = $this->resolveInstallmentDueDate($anchor);

        while ($firstDate->lt($start) && $interval > 0) {
            $offset += $interval;
            $firstDate = $this->resolveInstallmentDueDate(
                $anchor->copy()->addMonthsNoOverflow($offset)
            );
        }

        if ($firstDate->lt($start)) {
            return [];
        }

        $dates = [];
        for ($index = 0; $index < $totalInstallments; $index++) {
            $dueDate = $this->resolveInstallmentDueDate(
                $anchor->copy()->addMonthsNoOverflow($offset + ($index * $interval))
            );

            if ($end && $dueDate->gt($end)) {
                throw new \InvalidArgumentException(
                    'The selected installment quantity does not fit within the contract period.'
                );
            }

            $dates[] = $dueDate;
        }

        return $dates;
    }

    // ── Computed attributes ─────────────────────────────────────────

    public function getNextDueDateAttribute()
    {
        return $this->installments()
            ->pending()
            ->orderBy('due_date', 'asc')
            ->value('due_date');
    }

    // ── Amendment side-effects ──────────────────────────────────────

    /**
     * Apply readjustment side-effects: update contract installment_value
     * and recalculate expected_value of pending installments from effective_date.
     *
     * @return array Summary with keys: updated_count, old_value, new_value
     */
    public function applyReadjustment(ContractAmendment $amendment): array
    {
        $oldValue = $this->installment_value;

        $this->installment_value = $amendment->new_value;
        if (! $this->save()) {
            throw new \RuntimeException('Contract amendment update failed');
        }

        $pendingInstallments = $this->installments()
            ->pending()
            ->where('due_date', '>=', $amendment->effective_date)
            ->get();

        $updatedCount = 0;
        foreach ($pendingInstallments as $installment) {
            $installment->expected_value = $amendment->new_value;
            if (! $installment->save()) {
                throw new \RuntimeException('Installment amendment update failed');
            }
            $updatedCount++;
        }

        return [
            'updated_count' => $updatedCount,
            'old_value'     => $oldValue,
            'new_value'     => $amendment->new_value,
        ];
    }

    /**
     * Apply renewal side-effects: update contract end_date
     * and generate new installments for the extended period.
     *
     * @return array Summary with keys: new_end_date, generated_count
     */
    public function applyRenewal(ContractAmendment $amendment): array
    {
        // Guard: old_end_date is required for safe renewal
        if (! $amendment->old_end_date) {
            throw new \LogicException(
                'Contract renewal requires old_end_date to prevent installment duplication.'
            );
        }

        $this->end_date = $amendment->new_end_date;

        if ($amendment->new_value) {
            $this->installment_value = $amendment->new_value;
        }

        if (! $this->save()) {
            throw new \RuntimeException('Contract amendment update failed');
        }

        $generationStart = $amendment->old_end_date->copy()->addDay();

        $generatedCount = $this->generateRecurringInstallments(
            ContractStatusLabel::defaultForMetaType('installment', 'pending'),
            $generationStart,
            0
        );

        return [
            'new_end_date'    => $amendment->new_end_date,
            'generated_count' => $generatedCount,
        ];
    }

    /**
     * Apply termination side-effects: cancel pending installments after effective_date
     * and change contract status to cancelled.
     *
     * @return array Summary with keys: cancelled_count, kept_overdue_count
     */
    public function applyTermination(ContractAmendment $amendment): array
    {
        $defaultCancelledInstallment = ContractStatusLabel::defaultForMetaType('installment', 'cancelled');
        $defaultCancelledContract = ContractStatusLabel::defaultForMetaType('contract', 'cancelled');

        $pendingToCancel = $this->installments()
            ->pending()
            ->where('due_date', '>', $amendment->effective_date)
            ->get();

        $cancelledCount = 0;
        foreach ($pendingToCancel as $installment) {
            $installment->status_label_id = $defaultCancelledInstallment->id;
            if (! $installment->save()) {
                throw new \RuntimeException('Installment amendment update failed');
            }
            $cancelledCount++;
        }

        $keptOverdueCount = $this->installments()->overdue()->count();

        $this->status_label_id = $defaultCancelledContract->id;
        if (! $this->save()) {
            throw new \RuntimeException('Contract amendment update failed');
        }

        return [
            'cancelled_count'   => $cancelledCount,
            'kept_overdue_count' => $keptOverdueCount,
        ];
    }

    // ── Deletable ───────────────────────────────────────────────────

    public function isDeletable(): bool
    {
        return Gate::allows('delete', $this)
            && ($this->installments()->paid()->count() === 0)
            && ($this->deleted_at === null);
    }
}

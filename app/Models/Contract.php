<?php

namespace App\Models;

use App\Http\Traits\UniqueUndeletedTrait;
use App\Models\Traits\CompanyableTrait;
use App\Models\Traits\HasUploads;
use App\Models\Traits\Loggable;
use App\Models\Traits\Searchable;
use App\Presenters\ContractPresenter;
use App\Presenters\Presentable;
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
        'status_label_id',
        'supplier_id',
        'company_id',
        'start_date',
        'end_date',
        'billing_cycle',
        'billing_day',
        'installment_value',
        'total_value',
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
        'total_installments' => 'integer',
        'readjustment_month' => 'integer',
        'billing_day'        => 'integer',
        'supplier_id'        => 'integer',
        'company_id'         => 'integer',
        'status_label_id'    => 'integer',
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
        'billing_day'        => 'nullable|integer|min:1|max:28',
        'installment_value'  => 'required|numeric|min:0',
        'total_value'        => 'nullable|numeric|min:0',
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

    /**
     * Generate installments based on contract type and billing cycle.
     */
    public function generateInstallments(?Carbon $from = null): int
    {
        $defaultStatus = ContractStatusLabel::defaultForMetaType('installment', 'pending');
        if (! $defaultStatus) {
            return 0;
        }

        $count = 0;

        if ($this->contract_type === 'one_time') {
            return $this->generateOneTimeInstallments($defaultStatus, $count);
        }

        return $this->generateRecurringInstallments($defaultStatus, $from, $count);
    }

    protected function generateOneTimeInstallments(ContractStatusLabel $defaultStatus, int $count): int
    {
        $totalInstallments = $this->total_installments ?: 1;
        $valuePerInstallment = $this->total_value
            ? round($this->total_value / $totalInstallments, 2)
            : $this->installment_value;

        for ($i = 1; $i <= $totalInstallments; $i++) {
            $dueDate = $this->start_date->copy()->addMonths($i - 1);

            // Adjust due_date to billing_day if set
            if ($this->billing_day) {
                $adjustedDay = min($this->billing_day, $dueDate->daysInMonth);
                $dueDate->day($adjustedDay);
            }

            $this->installments()->create([
                'installment_number' => $i,
                'reference_date'     => $dueDate->copy()->startOfMonth(),
                'due_date'           => $dueDate,
                'expected_value'     => $valuePerInstallment,
                'status_label_id'    => $defaultStatus->id,
                'created_by'         => auth()->id(),
            ]);
            $count++;
        }

        return $count;
    }

    protected function generateRecurringInstallments(ContractStatusLabel $defaultStatus, ?Carbon $from, int $count): int
    {
        $start = $from ?: $this->start_date->copy();
        $end = $this->end_date;

        if (! $end) {
            // Contratos sem data final: gerar 12 meses a partir do início
            $end = $start->copy()->addMonths(11)->endOfMonth();
        }

        $monthsInterval = match ($this->billing_cycle) {
            'monthly'    => 1,
            'quarterly'  => 3,
            'semiannual' => 6,
            'annual'     => 12,
            default      => 1,
        };

        $existingCount = $this->installments()->count();
        $current = $start->copy();
        $number = $existingCount;

        // Guard: avoid generating in period already covered by existing installments
        $lastInstallment = $this->installments()->latest('due_date')->first();
        if ($lastInstallment && $start->lte($lastInstallment->due_date)) {
            $start = $lastInstallment->due_date->copy()->addDay();
            $current = $start->copy();
        }

        while ($current->lte($end)) {
            $number++;

            // Adjust due_date to billing_day if set
            $dueDate = $current->copy();
            if ($this->billing_day) {
                $adjustedDay = min($this->billing_day, $dueDate->daysInMonth);
                $dueDate->day($adjustedDay);
            }

            $this->installments()->create([
                'installment_number' => $number,
                'reference_date'     => $current->copy()->startOfMonth(),
                'due_date'           => $dueDate,
                'expected_value'     => $this->installment_value,
                'status_label_id'    => $defaultStatus->id,
                'created_by'         => auth()->id(),
            ]);
            $count++;
            $current->addMonths($monthsInterval);
        }

        return $count;
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
        $this->save();

        $pendingInstallments = $this->installments()
            ->pending()
            ->where('due_date', '>=', $amendment->effective_date)
            ->get();

        $updatedCount = 0;
        foreach ($pendingInstallments as $installment) {
            $installment->expected_value = $amendment->new_value;
            $installment->save();
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

        $this->save();

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
            $installment->save();
            $cancelledCount++;
        }

        $keptOverdueCount = $this->installments()->overdue()->count();

        $this->status_label_id = $defaultCancelledContract->id;
        $this->save();

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

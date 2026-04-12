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
            return 0;
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

        while ($current->lte($end)) {
            $number++;
            $this->installments()->create([
                'installment_number' => $number,
                'reference_date'     => $current->copy()->startOfMonth(),
                'due_date'           => $current->copy(),
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

    // ── Deletable ───────────────────────────────────────────────────

    public function isDeletable(): bool
    {
        return Gate::allows('delete', $this)
            && ($this->installments()->paid()->count() === 0)
            && ($this->deleted_at === null);
    }
}

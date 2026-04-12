<?php

namespace App\Models;

use App\Models\Traits\CompanyableChildTrait;
use App\Models\Traits\HasUploads;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

class ContractInstallment extends SnipeModel implements ICompanyableChild
{
    use CompanyableChildTrait;
    use HasFactory;
    use HasUploads;
    use Loggable;
    use SoftDeletes;
    use ValidatingTrait;

    protected $table = 'contract_installments';

    protected $fillable = [
        'contract_id',
        'installment_number',
        'reference_date',
        'due_date',
        'expected_value',
        'paid_value',
        'payment_date',
        'payment_method',
        'status_label_id',
        'ticket_reference',
        'notes',
    ];

    protected $casts = [
        'reference_date'     => 'date',
        'due_date'           => 'date',
        'payment_date'       => 'date',
        'expected_value'     => 'decimal:2',
        'paid_value'         => 'decimal:2',
        'installment_number' => 'integer',
        'contract_id'        => 'integer',
        'status_label_id'    => 'integer',
    ];

    protected $rules = [
        'contract_id'        => 'required|exists:contracts,id',
        'installment_number' => 'required|integer|min:1',
        'reference_date'     => 'required|date',
        'due_date'           => 'required|date',
        'expected_value'     => 'required|numeric|min:0',
        'paid_value'         => 'nullable|numeric|min:0',
        'payment_date'       => 'nullable|date',
        'payment_method'     => 'nullable|max:100|string',
        'status_label_id'    => 'required|exists:contract_status_labels,id',
        'ticket_reference'   => 'nullable|max:100|string',
        'notes'              => 'nullable|string',
    ];

    // ── CompanyableChild ────────────────────────────────────────────

    public function getCompanyableParents()
    {
        return ['contract'];
    }

    // ── Relationships ───────────────────────────────────────────────

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function adminuser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel()
    {
        return $this->belongsTo(ContractStatusLabel::class, 'status_label_id');
    }

    // ── Scopes by meta_type ─────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('installment', 'pending'));
    }

    public function scopePaid($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('installment', 'paid'));
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('installment', 'overdue'));
    }

    public function scopeCancelled($query)
    {
        return $query->whereIn('status_label_id', ContractStatusLabel::idsForMetaType('installment', 'cancelled'));
    }
}

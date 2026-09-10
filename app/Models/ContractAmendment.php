<?php

namespace App\Models;

use App\Models\Traits\CompanyableChildTrait;
use App\Models\Traits\HasUploads;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

class ContractAmendment extends SnipeModel implements ICompanyableChild
{
    use CompanyableChildTrait;
    use HasFactory;
    use HasUploads;
    use Loggable;
    use SoftDeletes;
    use ValidatingTrait;

    protected $table = 'contract_amendments';

    protected $fillable = [
        'contract_id',
        'amendment_type',
        'description',
        'rectifies_amendment_id',
        'old_value',
        'new_value',
        'old_end_date',
        'new_end_date',
        'effective_date',
        'ticket_reference',
        'notes',
    ];

    protected $casts = [
        'old_end_date'   => 'date',
        'new_end_date'   => 'date',
        'effective_date' => 'date',
        'old_value'      => 'decimal:2',
        'new_value'      => 'decimal:2',
        'contract_id'    => 'integer',
        'rectifies_amendment_id' => 'integer',
    ];

    protected $rules = [
        'contract_id'     => 'required|exists:contracts,id',
        'amendment_type'  => 'required|in:readjustment,scope_change,renewal,termination',
        'description'     => 'required|string',
        'old_value'       => 'nullable|numeric|min:0',
        'new_value'       => 'nullable|numeric|min:0',
        'old_end_date'    => 'nullable|date',
        'new_end_date'    => 'nullable|date',
        'effective_date'  => 'required|date',
        'ticket_reference' => 'nullable|max:100|string',
        'notes'           => 'nullable|string',
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

    /**
     * These amendment types already change contract/installment state when
     * created. A simple delete would hide the document while keeping those
     * effects, so corrections must use a tracked flow from the audit work.
     */
    public function hasAppliedEffects(): bool
    {
        return in_array($this->amendment_type, [
            'readjustment',
            'renewal',
            'termination',
        ], true);
    }

    public function isDocumentaryOnly(): bool
    {
        return $this->amendment_type === 'scope_change';
    }
}

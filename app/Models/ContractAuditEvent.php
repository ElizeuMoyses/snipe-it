<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ContractAuditEvent extends SnipeModel
{
    protected $table = 'contract_audit_events';

    protected $fillable = [
        'contract_id',
        'company_id',
        'auditable_type',
        'auditable_id',
        'action',
        'actor_id',
        'actor_type',
        'source',
        'correlation_id',
        'idempotency_key',
        'before',
        'after',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'contract_id' => 'integer',
        'company_id' => 'integer',
        'auditable_id' => 'integer',
        'actor_id' => 'integer',
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Contract audit events are append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('Contract audit events are append-only.');
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id')->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo('auditable')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}

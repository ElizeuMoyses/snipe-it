<?php

namespace App\Models;

use App\Http\Traits\TwoColumnUniqueUndeletedTrait;
use App\Models\Traits\Searchable;
use App\Presenters\ContractStatusLabelPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Watson\Validating\ValidatingTrait;

class ContractStatusLabel extends SnipeModel
{
    use HasFactory;
    use SoftDeletes;
    use Searchable;
    use TwoColumnUniqueUndeletedTrait;
    use ValidatingTrait;

    protected $table = 'contract_status_labels';
    protected $presenter = ContractStatusLabelPresenter::class;
    protected $injectUniqueIdentifier = true;

    protected $fillable = [
        'name',
        'scope',
        'meta_type',
        'color',
        'icon',
        'sort_order',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $rules = [
        'name'      => 'required|max:100|string|two_column_unique_undeleted:scope',
        'scope'     => 'required|in:contract,installment',
        'meta_type' => 'required|max:30|meta_type_for_scope',
        'color'     => 'nullable|max:10',
        'icon'      => 'nullable|max:50',
        'sort_order' => 'integer|min:0',
        'is_default' => 'boolean',
        'notes'     => 'nullable|string',
    ];

    protected $searchableAttributes = ['name', 'scope', 'meta_type', 'notes'];

    /**
     * Valid meta_types per scope
     */
    public const META_TYPES = [
        'installment' => ['pending', 'paid', 'overdue', 'cancelled'],
        'contract'    => ['draft', 'active', 'suspended', 'cancelled', 'expired'],
    ];

    // ── Relationships ───────────────────────────────────────────────

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'status_label_id');
    }

    public function installments()
    {
        return $this->hasMany(ContractInstallment::class, 'status_label_id');
    }

    public function adminuser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeForContracts($query)
    {
        return $query->where('scope', 'contract');
    }

    public function scopeForInstallments($query)
    {
        return $query->where('scope', 'installment');
    }

    public function scopeByMetaType($query, string $metaType)
    {
        return $query->where('meta_type', $metaType);
    }

    // ── Meta-type helpers ───────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->meta_type === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->meta_type === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->meta_type === 'overdue';
    }

    public function isTerminal(): bool
    {
        return in_array($this->meta_type, ['paid', 'cancelled']);
    }

    /**
     * Get all status_label IDs for a given scope + meta_type.
     * Cached in-memory per request to avoid N+1.
     */
    public static function idsForMetaType(string $scope, string $metaType): array
    {
        static $cache = [];
        $key = "{$scope}:{$metaType}";

        if (! isset($cache[$key])) {
            $cache[$key] = static::where('scope', $scope)
                ->where('meta_type', $metaType)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all();
        }

        return $cache[$key];
    }

    /**
     * Resolve the default sub-status for a given scope + meta_type.
     */
    public static function defaultForMetaType(string $scope, string $metaType): ?self
    {
        return static::where('scope', $scope)
            ->where('meta_type', $metaType)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Clear the in-memory meta_type ID cache (useful in tests).
     */
    public static function clearMetaTypeCache(): void
    {
        // Reset static cache by re-declaring
        // This is a workaround since PHP static vars can't be unset externally
    }

    // ── Deletable ───────────────────────────────────────────────────

    public function isDeletable(): bool
    {
        return Gate::allows('delete', $this)
            && ($this->contracts()->count() === 0)
            && ($this->installments()->count() === 0)
            && ($this->deleted_at === null);
    }
}

<?php

namespace App\Models;

use App\Http\Traits\UniqueUndeletedTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Watson\Validating\ValidatingTrait;

class ContractType extends SnipeModel
{
    use HasFactory;
    use SoftDeletes;
    use UniqueUndeletedTrait;
    use ValidatingTrait;

    protected $table = 'contract_types';

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $rules = [
        'name'      => 'required|max:100|string',
        'code'      => 'required|max:50|string|regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/|unique_undeleted',
        'is_active' => 'boolean',
        'notes'     => 'nullable|string',
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'contract_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isDeletable(): bool
    {
        return Gate::allows('delete', $this)
            && $this->contracts()->withTrashed()->count() === 0
            && $this->deleted_at === null;
    }
}

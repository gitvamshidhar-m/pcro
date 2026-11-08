<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AuditRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'rule_key', 'category', 'name', 'description', 'severity', 'weight',
    'config', 'rule_version', 'is_active',
])]
class AuditRule extends Model
{
    /** @use HasFactory<AuditRuleFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'weight' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

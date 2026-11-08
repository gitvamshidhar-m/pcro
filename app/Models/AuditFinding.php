<?php

namespace App\Models;

use App\Enums\AuditCategory;
use App\Enums\Severity;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AuditFindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'workspace_id', 'crawl_id', 'page_id', 'rule_key', 'category', 'title',
    'description', 'severity', 'confidence', 'evidence', 'recommended_improvement',
    'fingerprint', 'status', 'source',
])]
class AuditFinding extends Model
{
    /** @use HasFactory<AuditFindingFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'category' => AuditCategory::class,
            'severity' => Severity::class,
            'confidence' => 'integer',
            'evidence' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function opportunity(): HasOne
    {
        return $this->hasOne(Opportunity::class, 'finding_id');
    }

    public function scopeCategory(Builder $query, AuditCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}

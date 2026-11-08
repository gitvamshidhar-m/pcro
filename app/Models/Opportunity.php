<?php

namespace App\Models;

use App\Enums\AuditCategory;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id', 'crawl_id', 'page_id', 'finding_id', 'title', 'description',
    'category', 'impact', 'confidence', 'reach', 'funnel_relevance', 'effort',
    'score', 'evidence', 'heuristic', 'rank',
])]
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'category' => AuditCategory::class,
            'impact' => 'integer',
            'confidence' => 'integer',
            'reach' => 'integer',
            'funnel_relevance' => 'integer',
            'effort' => 'integer',
            'score' => 'float',
            'evidence' => 'array',
            'heuristic' => 'array',
            'rank' => 'integer',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'finding_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}

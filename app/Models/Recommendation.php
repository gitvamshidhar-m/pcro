<?php

namespace App\Models;

use App\Enums\RecommendationPriority;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'crawl_id', 'opportunity_id', 'ai_analysis_id', 'source',
    'title', 'description', 'rationale', 'action_steps', 'ab_test', 'priority',
    'evidence_refs', 'is_validated', 'validation_notes',
])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'priority' => RecommendationPriority::class,
            'action_steps' => 'array',
            'ab_test' => 'array',
            'evidence_refs' => 'array',
            'is_validated' => 'boolean',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class);
    }
}

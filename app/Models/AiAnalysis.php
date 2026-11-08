<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\AiAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'crawl_id', 'provider', 'model', 'prompt_version', 'input_hash',
    'status', 'request_meta', 'response', 'error', 'prompt_tokens',
    'completion_tokens', 'cost_usd', 'duration_ms',
])]
class AiAnalysis extends Model
{
    /** @use HasFactory<AiAnalysisFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'request_meta' => 'array',
            'response' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost_usd' => 'float',
            'duration_ms' => 'integer',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }
}

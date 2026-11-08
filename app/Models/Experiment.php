<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'website_id', 'crawl_id', 'name', 'hypothesis', 'status',
    'variant_config', 'results', 'started_at', 'ended_at',
])]
class Experiment extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'variant_config' => 'array',
            'results' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}

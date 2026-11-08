<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\PageMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'page_id', 'lighthouse', 'axe', 'vitals', 'image_stats',
    'js_errors', 'broken_links', 'availability', 'captured_at',
])]
class PageMetric extends Model
{
    /** @use HasFactory<PageMetricFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'lighthouse' => 'array',
            'axe' => 'array',
            'vitals' => 'array',
            'image_stats' => 'array',
            'js_errors' => 'array',
            'broken_links' => 'array',
            'availability' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}

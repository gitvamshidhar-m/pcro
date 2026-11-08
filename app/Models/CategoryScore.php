<?php

namespace App\Models;

use App\Enums\AuditCategory;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CategoryScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id', 'crawl_id', 'category', 'score', 'weight', 'components', 'rule_version',
])]
class CategoryScore extends Model
{
    /** @use HasFactory<CategoryScoreFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'category' => AuditCategory::class,
            'score' => 'float',
            'weight' => 'float',
            'components' => 'array',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }
}

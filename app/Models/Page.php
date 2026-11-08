<?php

namespace App\Models;

use App\Enums\PageType;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'workspace_id', 'crawl_id', 'url', 'url_hash', 'path', 'page_type', 'depth',
    'status_code', 'title', 'meta_description', 'canonical_url', 'h1', 'word_count',
    'content_hash', 'is_rendered', 'extraction', 'screenshot_path',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'page_type' => PageType::class,
            'is_rendered' => 'boolean',
            'extraction' => 'array',
            'depth' => 'integer',
            'status_code' => 'integer',
            'word_count' => 'integer',
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

    public function elements(): HasMany
    {
        return $this->hasMany(PageElement::class)->orderBy('position');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(PageMetric::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function scopeConversionCritical(Builder $query): Builder
    {
        return $query->whereIn('page_type', array_map(
            fn (PageType $type): string => $type->value,
            PageType::conversionCritical(),
        ));
    }

    public function elementsOfType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->elements->where('type', $type)->values();
    }
}

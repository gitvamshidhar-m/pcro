<?php

namespace App\Models;

use App\Enums\PageType;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\WebsiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'domain', 'url', 'label', 'favicon', 'metadata'])]
class Website extends Model
{
    /** @use HasFactory<WebsiteFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function crawls(): HasManyThrough
    {
        return $this->hasManyThrough(Crawl::class, Project::class);
    }

    public function latestProject(): ?Project
    {
        return $this->projects()->latest('id')->first();
    }

    public function keyPageType(): string
    {
        return PageType::Home->value;
    }
}

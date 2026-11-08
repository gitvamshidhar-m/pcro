<?php

namespace App\Models;

use App\Enums\CrawlStatus;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CrawlFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

#[Fillable([
    'workspace_id', 'project_id', 'created_by', 'status', 'stage', 'progress',
    'pages_requested', 'max_depth', 'pages_discovered', 'pages_crawled', 'pages_failed',
    'queued_at', 'started_at', 'completed_at', 'failed_at', 'error_message',
    'worker_output_path', 'summary', 'settings', 'cro_score', 'friction_score',
    'scoring_version', 'duration_ms',
])]
class Crawl extends Model
{
    /** @use HasFactory<CrawlFactory> */
    use HasFactory, HasPublicUlid;

    protected function casts(): array
    {
        return [
            'status' => CrawlStatus::class,
            'summary' => 'array',
            'settings' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'pages_requested' => 'integer',
            'max_depth' => 'integer',
            'pages_discovered' => 'integer',
            'pages_crawled' => 'integer',
            'pages_failed' => 'integer',
            'progress' => 'integer',
            'cro_score' => 'float',
            'friction_score' => 'float',
            'duration_ms' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function website(): HasOneThrough
    {
        return $this->hasOneThrough(
            Website::class,
            Project::class,
            'id',
            'id',
            'project_id',
            'website_id',
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function categoryScores(): HasMany
    {
        return $this->hasMany(CategoryScore::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class)->orderByDesc('score');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class)->latestOfMany();
    }

    public function aiAnalysis(): HasOne
    {
        return $this->hasOne(AiAnalysis::class)->latestOfMany();
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', CrawlStatus::Completed);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [CrawlStatus::Queued, CrawlStatus::Running]);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function progressLabel(): string
    {
        return match ($this->stage) {
            'queued' => 'Queued',
            'validating' => 'Validating URL',
            'crawling' => 'Crawling website',
            'extracting' => 'Extracting content',
            'technical' => 'Running technical audit',
            'auditing' => 'Running CRO audits',
            'scoring' => 'Scoring opportunities',
            'ai' => 'Generating AI analysis',
            'reporting' => 'Building report',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => ucfirst($this->stage),
        };
    }
}

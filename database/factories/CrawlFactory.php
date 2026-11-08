<?php

namespace Database\Factories;

use App\Enums\CrawlStatus;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crawl>
 */
class CrawlFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'project_id' => Project::factory(),
            'created_by' => null,
            'status' => CrawlStatus::Queued,
            'stage' => 'queued',
            'progress' => 0,
            'pages_requested' => 30,
            'max_depth' => 3,
            'pages_discovered' => 0,
            'pages_crawled' => 0,
            'pages_failed' => 0,
            'queued_at' => now(),
            'summary' => null,
            'settings' => [],
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => CrawlStatus::Running,
            'stage' => 'crawling',
            'progress' => 35,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => CrawlStatus::Completed,
            'stage' => 'completed',
            'progress' => 100,
            'pages_discovered' => 8,
            'pages_crawled' => 8,
            'started_at' => now()->subMinutes(3),
            'completed_at' => now(),
            'cro_score' => 68.5,
            'friction_score' => 42.0,
            'scoring_version' => config('cro.version'),
            'summary' => [
                'site_name' => 'Example company',
                'inspected_pages' => 8,
                'failed_pages' => 0,
                'lighthouse_available' => true,
                'axe_available' => true,
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => CrawlStatus::Failed,
            'stage' => 'failed',
            'progress' => 15,
            'started_at' => now()->subMinutes(1),
            'failed_at' => now(),
            'error_message' => 'The website could not be reached.',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Crawl;
use App\Models\Project;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Crawler\CrawlJobBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlJobBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function crawl(array $settings = [], int $pagesRequested = 30, int $maxDepth = 3): Crawl
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create([
            'target_url' => 'https://example.com/pricing',
        ]);

        return Crawl::factory()->for($workspace)->for($project)->create([
            'pages_requested' => $pagesRequested,
            'max_depth' => $maxDepth,
            'settings' => $settings,
        ]);
    }

    public function test_it_builds_a_complete_worker_payload(): void
    {
        $crawl = $this->crawl(['max_pages' => 12, 'max_depth' => 2]);

        $payload = app(CrawlJobBuilder::class)->build($crawl);

        $this->assertSame($crawl->ulid, $payload['job_id']);
        $this->assertSame('https://example.com/pricing', $payload['url']);
        $this->assertSame(12, $payload['max_pages']);
        $this->assertSame(2, $payload['max_depth']);
        $this->assertTrue($payload['respect_robots']);
        $this->assertTrue($payload['lighthouse']);
        $this->assertSame(1440, $payload['viewport']['width']);
        $this->assertSame(390, $payload['mobile_viewport']['width']);
        $this->assertStringEndsWith($crawl->ulid, $payload['output_dir']);
        $this->assertSame('crawls/'.$crawl->ulid, app(CrawlJobBuilder::class)->relativeOutputDirectory($crawl));
    }

    public function test_it_uses_the_settings_target_url_when_present(): void
    {
        $crawl = $this->crawl(['target_url' => 'https://example.com/landing-page']);

        $payload = app(CrawlJobBuilder::class)->build($crawl);

        $this->assertSame('https://example.com/landing-page', $payload['url']);
    }

    public function test_it_never_exceeds_the_configured_crawl_limits(): void
    {
        $crawl = $this->crawl(['max_pages' => 999, 'max_depth' => 99], pagesRequested: 200, maxDepth: 20);

        $payload = app(CrawlJobBuilder::class)->build($crawl);

        $this->assertSame((int) config('cro.crawl.max_pages'), $payload['max_pages']);
        $this->assertSame((int) config('cro.crawl.max_depth'), $payload['max_depth']);
    }

    public function test_it_can_disable_lighthouse_per_crawl(): void
    {
        $crawl = $this->crawl(['lighthouse' => false]);

        $payload = app(CrawlJobBuilder::class)->build($crawl);

        $this->assertFalse($payload['lighthouse']);
    }

    public function test_it_requires_a_target_url(): void
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create([
            'target_url' => 'https://example.com',
        ]);
        $project->forceFill(['target_url' => ''])->save();

        $crawl = Crawl::factory()->for($workspace)->for($project)->create([
            'settings' => [],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(CrawlJobBuilder::class)->build($crawl);
    }
}

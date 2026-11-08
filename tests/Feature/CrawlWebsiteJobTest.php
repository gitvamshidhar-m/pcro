<?php

namespace Tests\Feature;

use App\Contracts\CrawlRunner;
use App\Enums\CrawlStatus;
use App\Exceptions\CrawlFailedException;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Project;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Crawler\CrawlResult;
use App\Services\Crawler\CrawlResultPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlWebsiteJobTest extends TestCase
{
    use RefreshDatabase;

    private function crawl(CrawlStatus $status = CrawlStatus::Queued): Crawl
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create([
            'target_url' => 'https://example.com/',
        ]);

        return Crawl::factory()->for($workspace)->for($project)->create([
            'status' => $status,
            'stage' => $status === CrawlStatus::Queued ? 'queued' : 'completed',
            'settings' => ['target_url' => 'https://example.com/'],
            'summary' => ['requested_url' => 'https://example.com/'],
        ]);
    }

    private function runnerReturning(array $payload): CrawlRunner
    {
        return new class($payload) implements CrawlRunner
        {
            public function __construct(private array $payload) {}

            public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult
            {
                if ($onProgress !== null) {
                    $onProgress([
                        'stage' => 'crawling',
                        'progress' => 40,
                        'pages_discovered' => 3,
                        'pages_crawled' => 1,
                        'pages_failed' => 0,
                    ]);
                }

                return new CrawlResult($this->payload, 'output', null, 900);
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'ok' => true,
            'crawl' => ['domain' => 'example.com', 'has_ssl' => true],
            'stats' => [
                'pages_discovered' => 1,
                'pages_crawled' => 1,
                'pages_failed' => 0,
                'duration_ms' => 900,
            ],
            'pages' => [[
                'url' => 'https://example.com/',
                'final_url' => 'https://example.com/',
                'requested_url' => 'https://example.com/',
                'depth' => 0,
                'status_code' => 200,
                'title' => 'Example',
                'word_count' => 100,
            ]],
            'failures' => [],
        ];
    }

    public function test_it_marks_a_crawl_as_completed_and_persists_pages(): void
    {
        $crawl = $this->crawl();
        $runner = $this->runnerReturning($this->payload());

        (new CrawlWebsiteJob($crawl))->handle($runner, app(CrawlResultPersister::class));

        $crawl->refresh();

        $this->assertSame(CrawlStatus::Completed, $crawl->status);
        $this->assertSame('completed', $crawl->stage);
        $this->assertSame(100, $crawl->progress);
        $this->assertNotNull($crawl->completed_at);
        $this->assertSame(900, $crawl->duration_ms);
        $this->assertSame(1, Page::where('crawl_id', $crawl->id)->count());
    }

    public function test_it_marks_a_crawl_as_failed_and_exposes_a_safe_message(): void
    {
        $crawl = $this->crawl();

        $runner = new class implements CrawlRunner
        {
            public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult
            {
                throw new CrawlFailedException(
                    'browserType.launch: internal stack trace',
                    'The website could not be loaded. Try again later.',
                );
            }
        };

        try {
            (new CrawlWebsiteJob($crawl))->handle($runner, app(CrawlResultPersister::class));
            $this->fail('The job should rethrow the crawl failure.');
        } catch (CrawlFailedException) {
            // Expected.
        }

        $crawl->refresh();

        $this->assertSame(CrawlStatus::Failed, $crawl->status);
        $this->assertSame('failed', $crawl->stage);
        $this->assertSame('The website could not be loaded. Try again later.', $crawl->error_message);
        $this->assertNotNull($crawl->failed_at);
        $this->assertSame(0, Page::count());
    }

    public function test_a_terminal_crawl_is_not_processed_again(): void
    {
        $crawl = $this->crawl(CrawlStatus::Completed);

        $runner = new class implements CrawlRunner
        {
            public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult
            {
                throw new \RuntimeException('The runner should not be called.');
            }
        };

        (new CrawlWebsiteJob($crawl))->handle($runner, app(CrawlResultPersister::class));

        $this->assertSame(CrawlStatus::Completed, $crawl->fresh()->status);
    }

    public function test_it_records_crawler_progress_while_running(): void
    {
        $crawl = $this->crawl();

        $runner = new class implements CrawlRunner
        {
            public ?Crawl $observed = null;

            public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult
            {
                $onProgress([
                    'stage' => 'lighthouse',
                    'progress' => 70,
                    'pages_discovered' => 5,
                    'pages_crawled' => 5,
                    'pages_failed' => 1,
                    'current_url' => 'https://example.com/pricing',
                ]);

                $this->observed = Crawl::whereKey($crawl->id)->first();

                return new CrawlResult(['ok' => true, 'crawl' => [], 'stats' => [], 'pages' => [], 'failures' => []], 'output', null, 100);
            }
        };

        (new CrawlWebsiteJob($crawl))->handle($runner, app(CrawlResultPersister::class));

        $this->assertNotNull($runner->observed);
        $this->assertSame(CrawlStatus::Running, $runner->observed->status);
        $this->assertSame('technical', $runner->observed->stage);
        $this->assertSame(70, $runner->observed->progress);
        $this->assertSame(5, $runner->observed->pages_discovered);
        $this->assertSame('https://example.com/pricing', data_get($runner->observed->summary, 'current_url'));

        $this->assertSame(CrawlStatus::Completed, $crawl->fresh()->status);
    }
}

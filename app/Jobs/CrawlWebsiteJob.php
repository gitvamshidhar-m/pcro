<?php

namespace App\Jobs;

use App\Contracts\CrawlRunner;
use App\Exceptions\CrawlFailedException;
use App\Models\Crawl;
use App\Services\Crawler\CrawlResultPersister;
use App\Jobs\AnalyzeCrawlJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public Crawl $crawl)
    {
        $this->timeout = (int) config('cro.worker.max_runtime_seconds', 1200) + 120;
        $this->onQueue('crawls');
    }

    public function handle(CrawlRunner $runner, CrawlResultPersister $persister): void
    {
        $crawl = $this->crawl->fresh();

        if ($crawl === null || $crawl->status->isTerminal()) {
            return;
        }

        $crawl->forceFill([
            'status' => 'running',
            'stage' => 'crawling',
            'progress' => 5,
            'queued_at' => $crawl->queued_at ?? now(),
            'started_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        try {
            $result = $runner->run($crawl, function (array $progress) use ($crawl): void {
                $this->applyProgress($crawl->id, $progress);
            });

            $persister->persist($crawl, $result->payload);

            $crawl->forceFill([
                'status' => 'completed',
                'stage' => 'completed',
                'progress' => 100,
                'completed_at' => now(),
                'duration_ms' => $result->durationMs,
                'error_message' => null,
            ])->save();

            AnalyzeCrawlJob::dispatch($crawl->fresh());
        } catch (CrawlFailedException $exception) {
            $this->markFailed($crawl, $exception->publicMessage, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($crawl, 'The website analysis failed unexpectedly. Please retry.', $exception);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function applyProgress(int $crawlId, array $progress): void
    {
        $stage = (string) ($progress['stage'] ?? 'crawling');

        $mappedStage = match ($stage) {
            'crawling' => 'crawling',
            'mobile', 'lighthouse', 'links' => 'technical',
            'completed' => 'auditing',
            default => 'crawling',
        };

        $values = [
            'stage' => $mappedStage,
            'progress' => max(5, min(95, (int) ($progress['progress'] ?? 10))),
            'pages_discovered' => (int) ($progress['pages_discovered'] ?? 0),
            'pages_crawled' => (int) ($progress['pages_crawled'] ?? 0),
            'pages_failed' => (int) ($progress['pages_failed'] ?? 0),
        ];

        if (isset($progress['current_url'])) {
            $values['summary'] = array_merge($this->summary($crawlId), [
                'current_url' => mb_substr((string) $progress['current_url'], 0, 2000),
            ]);
        }

        Crawl::whereKey($crawlId)->update($values);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(int $crawlId): array
    {
        $summary = Crawl::whereKey($crawlId)->value('summary');

        if (is_string($summary)) {
            $decoded = json_decode($summary, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($summary) ? $summary : [];
    }

    private function markFailed(Crawl $crawl, string $publicMessage, Throwable $exception): void
    {
        $crawl->forceFill([
            'status' => 'failed',
            'stage' => 'failed',
            'failed_at' => now(),
            'error_message' => $publicMessage,
        ])->save();

        Log::error('Crawl failed', [
            'crawl_id' => $crawl->id,
            'crawl_ulid' => $crawl->ulid,
            'message' => $exception->getMessage(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $crawl = $this->crawl->fresh();

        if ($crawl !== null && ! $crawl->status->isTerminal()) {
            $this->markFailed($crawl, 'The website analysis failed unexpectedly. Please retry.', $exception ?? new \RuntimeException('Unknown crawl failure.'));
        }
    }
}

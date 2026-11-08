<?php

namespace App\Jobs;

use App\Models\Crawl;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeCrawlJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Crawl $crawl)
    {
        $this->onQueue('analysis');
    }

    public function handle(AnalysisPipeline $pipeline): void
    {
        $crawl = $this->crawl->fresh();

        if ($crawl === null || $crawl->status !== \App\Enums\CrawlStatus::Completed) {
            return;
        }

        $crawl->forceFill([
            'stage' => 'auditing',
            'progress' => 97,
        ])->save();

        try {
            $result = $pipeline->run($crawl);

            $crawl->forceFill([
                'stage' => 'completed',
                'progress' => 100,
                'error_message' => null,
            ])->save();

            Log::info('Crawl analysis completed', [
                'crawl_id' => $crawl->id,
                'crawl_ulid' => $crawl->ulid,
                'result' => $result,
            ]);
        } catch (Throwable $exception) {
            $summary = $crawl->summary ?? [];

            if (! is_array($summary)) {
                $summary = [];
            }

            $summary['analysis'] = [
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 1000),
                'failed_at' => now()->toIso8601String(),
            ];

            $crawl->forceFill([
                'stage' => 'analysis_failed',
                'summary' => $summary,
                'error_message' => 'The CRO analysis could not be completed. Please retry.',
            ])->save();

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $crawl = $this->crawl->fresh();

        if ($crawl === null) {
            return;
        }

        $summary = $crawl->summary ?? [];

        if (! is_array($summary)) {
            $summary = [];
        }

        $summary['analysis'] = [
            'status' => 'failed',
            'error' => $exception?->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ];

        $crawl->forceFill([
            'stage' => 'analysis_failed',
            'summary' => $summary,
            'error_message' => 'The CRO analysis could not be completed. Please retry.',
        ])->save();
    }
}

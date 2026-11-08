<?php

namespace App\Services\Crawler;

use App\Contracts\CrawlRunner;
use App\Exceptions\CrawlFailedException;
use App\Models\Crawl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to a separately deployed browser worker (see browser-worker/server.js).
 *
 * This is the deployment path for hosts that cannot run Node, Chromium or
 * Lighthouse. Screenshot files are only available when the worker and the
 * Laravel host share an output volume (CRO_WORKER_SHARED_ROOT).
 */
class HttpCrawlRunner implements CrawlRunner
{
    public function __construct(
        private readonly CrawlJobBuilder $jobBuilder,
    ) {}

    public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult
    {
        $startedAt = microtime(true);
        $baseUrl = rtrim((string) config('cro.worker.url'), '/');
        $token = (string) config('cro.worker.token');
        $maxRuntime = (int) config('cro.worker.max_runtime_seconds', 1200);

        if ($baseUrl === '') {
            throw new CrawlFailedException('No browser worker URL configured.', 'The browser worker is not configured.');
        }

        $job = $this->jobBuilder->build($crawl);
        $sharedRoot = config('cro.worker.shared_root');

        if (is_string($sharedRoot) && $sharedRoot !== '') {
            $job['output_dir'] = rtrim($sharedRoot, '/\\').DIRECTORY_SEPARATOR.'crawls'.DIRECTORY_SEPARATOR.$crawl->ulid;
        } else {
            unset($job['output_dir']);
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->post($baseUrl.'/v1/jobs', $job);

            if ($response->failed()) {
                throw new CrawlFailedException(
                    'The worker rejected the job: '.$response->body(),
                    'The browser worker rejected this analysis request.',
                    ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)],
                );
            }

            $jobId = $response->json('job.id');

            if (! is_string($jobId) || $jobId === '') {
                throw new CrawlFailedException('The worker did not return a job identifier.', 'The browser worker returned an invalid response.');
            }

            $deadline = microtime(true) + $maxRuntime;
            $lastSignature = null;

            while (microtime(true) < $deadline) {
                sleep(2);

                $status = Http::withToken($token)->acceptJson()->timeout(20)->get($baseUrl.'/v1/jobs/'.$jobId);

                if ($status->failed()) {
                    throw new CrawlFailedException(
                        'Could not read worker job status: '.$status->body(),
                        'The browser worker stopped responding.',
                    );
                }

                $jobState = $status->json('job', []);
                $progress = $status->json('progress');
                $signature = md5(json_encode([$jobState['status'] ?? null, md5(json_encode($progress) ?: '')]) ?: '');

                if ($progress !== null && $onProgress !== null && $signature !== $lastSignature) {
                    $lastSignature = $signature;
                    $onProgress($progress);
                }

                $state = $jobState['status'] ?? 'unknown';

                if ($state === 'failed' || $state === 'cancelled') {
                    throw new CrawlFailedException(
                        (string) ($jobState['error'] ?? 'The worker crawl failed.'),
                        $this->publicMessage((string) ($jobState['error'] ?? '')),
                        ['job' => $jobState],
                    );
                }

                if ($state !== 'completed') {
                    continue;
                }

                $result = Http::withToken($token)->acceptJson()->timeout(60)->get($baseUrl.'/v1/jobs/'.$jobId.'/result');

                if ($result->failed() || ! is_array($result->json('result'))) {
                    throw new CrawlFailedException(
                        'The worker completed without a readable result.',
                        'The browser worker did not return a usable analysis result.',
                    );
                }

                return new CrawlResult(
                    payload: $result->json('result'),
                    outputDirectory: (string) ($job['output_dir'] ?? storage_path('app/crawls/'.$crawl->ulid)),
                    resultPath: null,
                    durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                );
            }
        } catch (ConnectionException $exception) {
            throw new CrawlFailedException(
                'The browser worker is unreachable: '.$exception->getMessage(),
                'The browser worker is currently unreachable. Please retry shortly.',
            );
        }

        throw new CrawlFailedException('The browser worker timed out.', 'The website analysis timed out before it could finish.');
    }

    private function publicMessage(string $error): string
    {
        Log::warning('Remote crawl worker failed', ['error' => mb_substr($error, 0, 1000)]);

        return 'The website analysis could not be completed. Please retry, or try a different public URL.';
    }
}

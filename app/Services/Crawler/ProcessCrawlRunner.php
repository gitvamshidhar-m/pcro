<?php

namespace App\Services\Crawler;

use App\Contracts\CrawlRunner;
use App\Exceptions\CrawlFailedException;
use App\Models\Crawl;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ProcessCrawlRunner implements CrawlRunner
{
    public function __construct(
        private readonly CrawlJobBuilder $jobBuilder,
    ) {}

    public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult
    {
        $startedAt = microtime(true);
        $job = $this->jobBuilder->build($crawl);
        $outputDirectory = $job['output_dir'];

        File::ensureDirectoryExists($outputDirectory);

        foreach (['result.json', 'error.json', 'progress.json'] as $file) {
            File::delete($outputDirectory.DIRECTORY_SEPARATOR.$file);
        }

        $jobPath = $outputDirectory.DIRECTORY_SEPARATOR.'job.json';
        File::put($jobPath, json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $binary = (string) config('cro.worker.node_binary', 'node');
        $workerPath = (string) config('cro.worker.path', base_path('browser-worker'));
        $maxRuntime = (int) config('cro.worker.max_runtime_seconds', 1200);
        $pollInterval = max(200, (int) config('cro.worker.poll_interval_ms', 750)) * 1000;

        $process = new Process(
            [$binary, 'index.js', '--input', $jobPath, '--quiet'],
            $workerPath,
            null,
            null,
            $maxRuntime,
        );

        $process->setTimeout($maxRuntime);
        $process->start();

        $lastProgressSignature = null;

        while ($process->isRunning()) {
            $progress = $this->readJson($outputDirectory.DIRECTORY_SEPARATOR.'progress.json');

            if ($progress !== null) {
                $signature = md5(json_encode($progress) ?: '');

                if ($signature !== $lastProgressSignature) {
                    $lastProgressSignature = $signature;

                    if ($onProgress !== null) {
                        $onProgress($progress);
                    }
                }
            }

            usleep($pollInterval);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $resultPath = $outputDirectory.DIRECTORY_SEPARATOR.'result.json';
        $result = $this->readJson($resultPath);

        if ($process->getExitCode() !== 0 || $result === null || ($result['ok'] ?? false) !== true) {
            $error = $this->readJson($outputDirectory.DIRECTORY_SEPARATOR.'error.json');
            $message = $error['error']
                ?? $error['message']
                ?? $this->lastOutputLine($process->getErrorOutput())
                ?? $this->lastOutputLine($process->getOutput())
                ?? 'The browser worker did not return a crawl result.';

            throw new CrawlFailedException(
                trim((string) $message),
                $this->publicMessage($error),
                [
                    'exit_code' => $process->getExitCode(),
                    'error' => $error,
                    'stderr' => mb_substr($process->getErrorOutput(), 0, 4000),
                ],
            );
        }

        return new CrawlResult(
            payload: $result,
            outputDirectory: $outputDirectory,
            resultPath: $resultPath,
            durationMs: $durationMs,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function lastOutputLine(string $output): ?string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));

        return $lines === [] ? null : end($lines);
    }

    /**
     * @param  array<string, mixed>|null  $error
     */
    private function publicMessage(?array $error): string
    {
        $code = $error['code'] ?? null;

        return match ($code) {
            'invalid_job' => 'The crawl request was rejected because its settings are invalid.',
            'homepage_failed' => 'The homepage could not be loaded. Check that the site is publicly reachable and not blocking automated browsers.',
            'dns_failure', 'private_dns', 'blocked_host', 'private_ip' => 'The website could not be reached safely because it does not resolve to a public address.',
            default => 'The website analysis could not be completed. Please retry, or try a different public URL.',
        };
    }
}

<?php

namespace App\Services\Crawler;

final readonly class CrawlResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $outputDirectory,
        public ?string $resultPath,
        public int $durationMs,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pages(): array
    {
        return $this->payload['pages'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return $this->payload['stats'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function site(): array
    {
        return $this->payload['crawl'] ?? [];
    }
}

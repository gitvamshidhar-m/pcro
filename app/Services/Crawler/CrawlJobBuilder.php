<?php

namespace App\Services\Crawler;

use App\Models\Crawl;

class CrawlJobBuilder
{
    /**
     * Build the JSON-safe job payload expected by browser-worker/index.js.
     *
     * @return array<string, mixed>
     */
    public function build(Crawl $crawl): array
    {
        $crawl->loadMissing(['project.website']);
        $settings = $crawl->settings ?? [];
        $targetUrl = $settings['target_url']
            ?? $crawl->project?->target_url
            ?? $crawl->project?->website?->url;

        if (! is_string($targetUrl) || $targetUrl === '') {
            throw new \InvalidArgumentException('The crawl has no target URL.');
        }

        $viewport = config('cro.crawl.viewport');
        $mobileViewport = config('cro.crawl.mobile_viewport');

        return [
            'job_id' => $crawl->ulid,
            'url' => $targetUrl,
            'output_dir' => $this->outputDirectory($crawl),
            'max_pages' => max(1, min((int) ($settings['max_pages'] ?? $crawl->pages_requested), (int) config('cro.crawl.max_pages'))),
            'max_depth' => max(0, min((int) ($settings['max_depth'] ?? $crawl->max_depth), (int) config('cro.crawl.max_depth'))),
            'nav_timeout_ms' => (int) config('cro.crawl.nav_timeout_ms'),
            'page_timeout_ms' => (int) config('cro.crawl.page_timeout_ms'),
            'delay_ms' => (int) config('cro.crawl.delay_ms'),
            'max_retries' => (int) config('cro.crawl.max_retries'),
            'respect_robots' => (bool) config('cro.crawl.respect_robots'),
            'screenshot_quality' => (int) config('cro.crawl.screenshot_quality'),
            'user_agent' => (string) config('cro.crawl.user_agent'),
            'viewport' => [
                'width' => (int) ($viewport['width'] ?? 1440),
                'height' => (int) ($viewport['height'] ?? 1000),
            ],
            'mobile_viewport' => [
                'width' => (int) ($mobileViewport['width'] ?? 390),
                'height' => (int) ($mobileViewport['height'] ?? 844),
            ],
            'lighthouse' => (bool) ($settings['lighthouse'] ?? config('cro.crawl.lighthouse_enabled')),
            'lighthouse_timeout_ms' => (int) config('cro.crawl.lighthouse_timeout_ms'),
            'broken_link_checks' => (int) config('cro.crawl.broken_link_checks'),
            'discover_sitemaps' => (bool) ($settings['discover_sitemaps'] ?? true),
        ];
    }

    public function outputDirectory(Crawl $crawl): string
    {
        return storage_path('app/crawls/'.$crawl->ulid);
    }

    public function relativeOutputDirectory(Crawl $crawl): string
    {
        return 'crawls/'.$crawl->ulid;
    }
}

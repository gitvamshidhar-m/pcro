<?php

namespace App\Contracts;

use App\Exceptions\CrawlFailedException;
use App\Models\Crawl;
use App\Services\Crawler\CrawlResult;

interface CrawlRunner
{
    /**
     * Execute a crawl and return its structured result.
     *
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     *
     * @throws CrawlFailedException
     */
    public function run(Crawl $crawl, ?callable $onProgress = null): CrawlResult;
}

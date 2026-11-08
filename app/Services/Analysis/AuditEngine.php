<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;

interface AuditEngine
{
    public function category(): AuditCategory;

    /**
     * @return array<int, CheckResult>
     */
    public function audit(CrawlAnalysisContext $context): array;
}

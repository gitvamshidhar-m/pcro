<?php

namespace App\Services\Analysis;

use App\Enums\CheckStatus;
use App\Models\Page;

abstract class AbstractAuditEngine implements AuditEngine
{
    public function __construct(
        protected readonly AuditRuleBook $ruleBook,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    protected function result(
        CrawlAnalysisContext $context,
        string $ruleKey,
        CheckStatus $status,
        array $evidence,
        ?Page $page = null,
        ?string $description = null,
        ?int $confidence = null,
    ): CheckResult {
        $category = $this->category();
        $rule = $this->ruleBook->rule($category, $ruleKey);
        $severity = $this->ruleBook->severity((string) ($rule['severity'] ?? 'medium'));

        if ($status === CheckStatus::Warning) {
            $severity = $severity->downgrade();
        }

        $title = (string) ($rule['finding'] ?? $rule['label'] ?? $ruleKey);

        if ($status === CheckStatus::Pass) {
            $title = (string) ($rule['label'] ?? $ruleKey);
        }

        $evidence = array_merge([
            'rule_key' => $ruleKey,
            'rule_version' => config('cro.version'),
            'inspected_pages' => $context->inspectedCount(),
        ], $evidence);

        return new CheckResult(
            ruleKey: $ruleKey,
            category: $category,
            status: $status,
            severity: $severity,
            confidence: $confidence ?? (int) ($rule['confidence'] ?? 70),
            title: $title,
            description: $description ?? $this->describe($status, $evidence),
            recommendation: (string) ($rule['recommendation'] ?? ''),
            evidence: $evidence,
            pageId: $page?->id,
            pageUrl: $page?->url,
            opportunity: (array) ($rule['opportunity'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function describe(CheckStatus $status, array $evidence): string
    {
        $observed = $evidence['observed'] ?? null;

        if (is_string($observed) && $observed !== '') {
            return $observed;
        }

        return match ($status) {
            CheckStatus::Pass => 'No issue detected for this check.',
            CheckStatus::Warning => 'This check needs attention.',
            CheckStatus::Fail => 'This check failed.',
            CheckStatus::NotApplicable => 'This check could not be assessed from the available evidence.',
        };
    }

    protected function textContains(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);

        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function countMatches(string $haystack, array $needles): int
    {
        $count = 0;
        $haystack = mb_strtolower($haystack);

        foreach ($needles as $needle) {
            $count += mb_substr_count($haystack, mb_strtolower($needle));
        }

        return $count;
    }

    protected function cleanSnippet(?string $value, int $limit = 180): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $value)), 0, $limit);
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    protected function limitUrls(array $urls, int $limit = 8): array
    {
        return array_values(array_slice(array_unique($urls), 0, $limit));
    }
}

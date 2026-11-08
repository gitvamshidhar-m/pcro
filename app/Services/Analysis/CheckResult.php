<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\CheckStatus;
use App\Enums\Severity;

final readonly class CheckResult
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, int>  $opportunity
     */
    public function __construct(
        public string $ruleKey,
        public AuditCategory $category,
        public CheckStatus $status,
        public Severity $severity,
        public int $confidence,
        public string $title,
        public string $description,
        public string $recommendation,
        public array $evidence,
        public ?int $pageId = null,
        public ?string $pageUrl = null,
        public array $opportunity = [],
    ) {}

    public function isIssue(): bool
    {
        return in_array($this->status, [CheckStatus::Warning, CheckStatus::Fail], true);
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->ruleKey,
            $this->pageUrl ?? 'site',
            mb_substr((string) ($this->evidence['observed'] ?? ''), 0, 500),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toComponent(): array
    {
        return [
            'rule_key' => $this->ruleKey,
            'status' => $this->status->value,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence,
            'title' => $this->title,
            'description' => $this->description,
            'observed' => $this->evidence['observed'] ?? null,
            'page_url' => $this->pageUrl,
        ];
    }
}

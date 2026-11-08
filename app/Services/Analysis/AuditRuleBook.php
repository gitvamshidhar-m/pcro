<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\Severity;

final class AuditRuleBook
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $categories;

    /**
     * @param  array<string, array<string, mixed>>|null  $categories
     */
    public function __construct(?array $categories = null)
    {
        /** @var array<string, array<string, mixed>> $categories */
        $categories = $categories ?? config('cro.categories', []);
        $this->categories = $categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function category(AuditCategory $category): array
    {
        return $this->categories[$category->value] ?? ['weight' => 0, 'checks' => []];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function checks(AuditCategory $category): array
    {
        return $this->category($category)['checks'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rule(AuditCategory $category, string $ruleKey): array
    {
        $rule = $this->checks($category)[$ruleKey] ?? null;

        if (! is_array($rule)) {
            throw new \InvalidArgumentException("Unknown audit rule [{$ruleKey}] for [{$category->value}].");
        }

        return $rule;
    }

    public function weight(AuditCategory $category): float
    {
        return (float) ($this->category($category)['weight'] ?? 0);
    }

    public function severity(string $value): Severity
    {
        return Severity::tryFrom($value) ?? Severity::Medium;
    }

    /**
     * @return array<int, string>
     */
    public function allRuleKeys(): array
    {
        $keys = [];

        foreach (AuditCategory::all() as $category) {
            foreach (array_keys($this->checks($category)) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}

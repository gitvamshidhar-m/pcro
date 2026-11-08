<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Models\CategoryScore;
use App\Models\Crawl;

final class CategoryScorer
{
    /**
     * @param  array<int, CheckResult>  $results
     * @return array{score: float|null, components: array<int, array<string, mixed>>}
     */
    public function score(AuditCategory $category, array $results): array
    {
        $components = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($results as $result) {
            $score = $result->status->score();

            $components[] = [
                'rule_key' => $result->ruleKey,
                'status' => $result->status->value,
                'score' => $score,
                'weight' => (float) ($this->ruleWeight($result->ruleKey, $category)),
                'confidence' => $result->confidence,
                'title' => $result->title,
                'page_url' => $result->pageUrl,
            ];

            if ($score === null) {
                continue;
            }

            $weight = $this->ruleWeight($result->ruleKey, $category);
            $weightedSum += $score * $weight;
            $weightTotal += $weight;
        }

        $score = $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;

        return [
            'score' => $score,
            'components' => $components,
        ];
    }

    private function ruleWeight(string $ruleKey, AuditCategory $category): float
    {
        $rule = config("cro.categories.{$category->value}.checks.{$ruleKey}", []);

        return (float) ($rule['weight'] ?? 1.0);
    }

    /**
     * Persist category scores and return the overall crawl score.
     *
     * @param  array<AuditCategory, array{score: float|null, components: array<int, array<string, mixed>>}>  $scores
     */
    public function persist(Crawl $crawl, array $scores): float
    {
        foreach ($scores as $category => $data) {
            if ($data['score'] === null) {
                continue;
            }

            $categoryEnum = $category instanceof AuditCategory
                ? $category
                : AuditCategory::from((string) $category);

            CategoryScore::updateOrCreate(
                ['crawl_id' => $crawl->id, 'category' => $categoryEnum->value],
                [
                    'workspace_id' => $crawl->workspace_id,
                    'score' => $data['score'],
                    'weight' => (float) config("cro.categories.{$categoryEnum->value}.weight", 0),
                    'components' => $data['components'],
                    'rule_version' => config('cro.version'),
                ],
            );
        }

        return $this->overall($scores);
    }

    /**
     * @param  array<AuditCategory, array{score: float|null, components: array<int, array<string, mixed>>}>  $scores
     */
    public function overall(array $scores): float
    {
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($scores as $category => $data) {
            if ($data['score'] === null) {
                continue;
            }

            $categoryEnum = $category instanceof AuditCategory
                ? $category
                : AuditCategory::from((string) $category);

            $weight = (float) config("cro.categories.{$categoryEnum->value}.weight", 0);
            $weightedSum += $data['score'] * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal === 0.0) {
            return 0.0;
        }

        return round($weightedSum / $weightTotal, 2);
    }
}

<?php

namespace App\Services\Analysis;

use App\Models\Crawl;

final class CrawlComparisonService
{
    /**
     * @return array<string, mixed>
     */
    public function compare(Crawl $base, Crawl $comparison): array
    {
        $base->loadMissing('categoryScores');
        $comparison->loadMissing('categoryScores');

        $baseScores = $base->categoryScores->keyBy(fn ($score) => $score->category->value);
        $comparisonScores = $comparison->categoryScores->keyBy(fn ($score) => $score->category->value);

        $categories = [];

        foreach (\App\Enums\AuditCategory::all() as $category) {
            $baseScore = $baseScores->get($category->value);
            $comparisonScore = $comparisonScores->get($category->value);

            $categories[$category->value] = [
                'label' => $category->label(),
                'base' => $baseScore?->score,
                'comparison' => $comparisonScore?->score,
                'delta' => ($baseScore && $comparisonScore)
                    ? round((float) $comparisonScore->score - (float) $baseScore->score, 2)
                    : null,
            ];
        }

        return [
            'base' => $base,
            'comparison' => $comparison,
            'cro_score' => [
                'base' => $base->cro_score,
                'comparison' => $comparison->cro_score,
                'delta' => ($base->cro_score !== null && $comparison->cro_score !== null)
                    ? round((float) $comparison->cro_score - (float) $base->cro_score, 2)
                    : null,
            ],
            'friction_score' => [
                'base' => $base->friction_score,
                'comparison' => $comparison->friction_score,
                'delta' => ($base->friction_score !== null && $comparison->friction_score !== null)
                    ? round((float) $comparison->friction_score - (float) $base->friction_score, 2)
                    : null,
            ],
            'categories' => $categories,
            'findings' => [
                'base' => $base->findings()->count(),
                'comparison' => $comparison->findings()->count(),
                'delta' => $comparison->findings()->count() - $base->findings()->count(),
            ],
            'opportunities' => [
                'base' => $base->opportunities()->count(),
                'comparison' => $comparison->opportunities()->count(),
                'delta' => $comparison->opportunities()->count() - $base->opportunities()->count(),
            ],
        ];
    }
}

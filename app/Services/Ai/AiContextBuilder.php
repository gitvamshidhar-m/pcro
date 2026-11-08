<?php

namespace App\Services\Ai;

use App\Models\Crawl;
use App\Models\AuditFinding;
use App\Models\Opportunity;

final class AiContextBuilder
{
    /**
     * @return array{json: string, hash: string, truncated: bool}
     */
    public function build(Crawl $crawl): array
    {
        $crawl->loadMissing(['project.website', 'findings', 'opportunities', 'categoryScores', 'pages']);

        $context = [
            'site' => [
                'domain' => $crawl->project?->website?->domain,
                'url' => data_get($crawl->settings, 'target_url'),
                'pages_inspected' => $crawl->pages->count(),
                'cro_score' => $crawl->cro_score,
                'friction_score' => $crawl->friction_score,
                'summary' => $crawl->summary,
            ],
            'category_scores' => $crawl->categoryScores->map(fn ($score) => [
                'category' => $score->category->value,
                'score' => $score->score,
                'components' => $score->components,
            ])->values()->all(),
            'findings' => $crawl->findings->map(fn (AuditFinding $finding) => [
                'id' => $finding->ulid,
                'rule_key' => $finding->rule_key,
                'category' => $finding->category->value,
                'severity' => $finding->severity->value,
                'confidence' => $finding->confidence,
                'title' => $finding->title,
                'description' => $finding->description,
                'recommended_improvement' => $finding->recommended_improvement,
                'evidence' => $finding->evidence,
                'page_url' => $finding->page?->url,
            ])->values()->all(),
            'opportunities' => $crawl->opportunities->take(15)->map(fn (Opportunity $opportunity) => [
                'id' => $opportunity->ulid,
                'title' => $opportunity->title,
                'category' => $opportunity->category->value,
                'score' => $opportunity->score,
                'impact' => $opportunity->impact,
                'confidence' => $opportunity->confidence,
                'reach' => $opportunity->reach,
                'funnel_relevance' => $opportunity->funnel_relevance,
                'effort' => $opportunity->effort,
                'evidence' => $opportunity->evidence,
            ])->values()->all(),
        ];

        $maxChars = (int) config('cro.ai.max_input_chars', 60000);
        $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $truncated = false;

        if (strlen($json) > $maxChars) {
            // Trim findings/opportunities until within budget.
            while (strlen($json) > $maxChars && count($context['findings']) > 5) {
                array_pop($context['findings']);
                $truncated = true;
                $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            while (strlen($json) > $maxChars && count($context['opportunities']) > 3) {
                array_pop($context['opportunities']);
                $truncated = true;
                $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            if (strlen($json) > $maxChars) {
                $context['site']['summary'] = null;
                $truncated = true;
                $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        return [
            'json' => $json,
            'hash' => hash('sha256', $json),
            'truncated' => $truncated,
        ];
    }
}

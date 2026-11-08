<?php

namespace App\Services\Analysis;

use App\Enums\RecommendationPriority;
use App\Models\Crawl;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

final class DeterministicRecommendationBuilder
{
    /**
     * @return Collection<int, Recommendation>
     */
    public function build(Crawl $crawl, Collection $opportunities): Collection
    {
        Recommendation::where('crawl_id', $crawl->id)->delete();

        $maxRecommendations = (int) config('cro.report.max_recommendations', 10);
        $maxRecommendations = min($maxRecommendations, (int) config('cro.ai.max_recommendations', 12));

        $created = collect();

        foreach ($opportunities->take($maxRecommendations) as $opportunity) {
            $finding = $opportunity->finding;
            $ruleKey = $finding?->rule_key;
            $ruleRecommendation = null;

            if ($finding !== null) {
                $ruleRecommendation = (string) (config("cro.categories.{$finding->category->value}.checks.{$finding->rule_key}.recommendation") ?? '');
            }

            $priority = $this->priority($finding?->severity?->value ?? 'medium');
            $pageUrl = $opportunity->page?->url ?? $finding?->page?->url;
            $urls = $pageUrl ? [$pageUrl] : [];
            $observed = data_get($finding?->evidence, 'observed');

            $actionSteps = array_values(array_filter([
                $observed ? 'Review the evidence: '.$observed : null,
                $ruleRecommendation !== '' ? 'Implement: '.$ruleRecommendation : ($opportunity->description ?: null),
                $pageUrl ? 'Apply the change on '.$pageUrl.' and any similar templates.' : 'Apply the change to the affected page templates.',
                'Verify the result on a 360px mobile viewport and a desktop viewport.',
                'Re-run the CRO analysis to confirm the finding is resolved.',
            ]));

            $recommendation = Recommendation::create([
                'workspace_id' => $crawl->workspace_id,
                'crawl_id' => $crawl->id,
                'opportunity_id' => $opportunity->id,
                'source' => 'heuristic',
                'title' => $opportunity->title,
                'description' => $opportunity->description,
                'rationale' => 'This is prioritised because '.mb_strtolower((string) $opportunity->description).' The heuristic score is '.number_format((float) $opportunity->score, 2).'.',
                'action_steps' => $actionSteps,
                'ab_test' => [
                    'hypothesis' => 'If the recommended change is implemented, the primary conversion metric should improve because the detected friction is reduced.',
                    'primary_metric' => $this->primaryMetric($opportunity->category->value),
                    'secondary_metrics' => ['bounce rate', 'time to first CTA interaction', 'form completion rate'],
                    'minimum_runtime' => '2-4 weeks or 1,000 qualifying conversions',
                    'note' => 'No uplift percentage is guaranteed; compare against the current baseline and keep the test running until significance is reached.',
                ],
                'priority' => $priority,
                'evidence_refs' => array_values(array_filter([
                    $finding?->ulid,
                    $opportunity->ulid,
                    $pageUrl,
                ])),
                'is_validated' => false,
            ]);

            $created->push($recommendation);
        }

        return $created;
    }

    private function priority(string $severity): RecommendationPriority
    {
        return match ($severity) {
            'critical' => RecommendationPriority::Critical,
            'high' => RecommendationPriority::High,
            'medium' => RecommendationPriority::Medium,
            default => RecommendationPriority::Low,
        };
    }

    private function primaryMetric(string $category): string
    {
        return match ($category) {
            'conversion' => 'form submission rate',
            'ux' => 'task completion rate',
            'trust' => 'contact rate',
            'technical' => 'engaged session rate',
            'messaging' => 'CTA click-through rate',
            default => 'conversion rate',
        };
    }
}

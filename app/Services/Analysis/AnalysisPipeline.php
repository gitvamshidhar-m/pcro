<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Models\AuditFinding;
use App\Models\Crawl;
use Illuminate\Support\Facades\DB;

final class AnalysisPipeline
{
    /**
     * @param  array<int, AuditEngine>  $engines
     */
    public function __construct(
        private readonly array $engines,
        private readonly AuditRuleBook $ruleBook,
        private readonly CategoryScorer $scorer,
        private readonly FrictionAnalyzer $friction,
        private readonly OpportunityBuilder $opportunities,
        private readonly DeterministicRecommendationBuilder $recommendations,
        private readonly ?\App\Services\Ai\AiAnalysisService $ai = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(Crawl $crawl): array
    {
        $context = CrawlAnalysisContext::from($crawl);

        $resultsByCategory = [];

        foreach ($this->engines as $engine) {
            $resultsByCategory[$engine->category()->value] = $engine->audit($context);
        }

        $findingCount = 0;
        $opportunityCount = 0;
        $recommendationCount = 0;

        DB::transaction(function () use (
            $crawl,
            $context,
            $resultsByCategory,
            &$findingCount,
            &$opportunityCount,
            &$recommendationCount,
        ): void {
            AuditFinding::where('crawl_id', $crawl->id)->delete();

            $findings = collect();

            foreach ($resultsByCategory as $results) {
                foreach ($results as $result) {
                    if (! $result instanceof CheckResult || ! $result->isIssue()) {
                        continue;
                    }

                    $finding = AuditFinding::create([
                        'workspace_id' => $crawl->workspace_id,
                        'crawl_id' => $crawl->id,
                        'page_id' => $result->pageId,
                        'rule_key' => $result->ruleKey,
                        'category' => $result->category,
                        'title' => $result->title,
                        'description' => $result->description,
                        'severity' => $result->severity,
                        'confidence' => $result->confidence,
                        'evidence' => $result->evidence,
                        'recommended_improvement' => $result->recommendation,
                        'fingerprint' => $result->fingerprint(),
                        'status' => 'open',
                        'source' => 'rule',
                    ]);

                    $findings->push($finding);
                    $findingCount++;
                }
            }

            $scores = [];

            foreach ($resultsByCategory as $category => $results) {
                $scores[$category] = $this->scorer->score(AuditCategory::from($category), $results);
            }

            $overall = $this->scorer->persist($crawl, $scores);

            $friction = $this->friction->analyze($context);

            $opportunities = $this->opportunities->build($crawl, $findings);
            $opportunityCount = $opportunities->count();

            $recommendations = $this->recommendations->build($crawl, $opportunities);
            $recommendationCount = $recommendations->count();

            $aiResult = null;

            if ($this->ai !== null) {
                $aiResult = $this->ai->run($crawl);
            }

            $crawl->refresh();

            $summary = $crawl->summary ?? [];

            $summary['analysis'] = [
                'status' => 'completed',
                'version' => config('cro.version'),
                'rule_version' => config('cro.version'),
                'checks' => array_map(fn (array $results): int => count($results), $resultsByCategory),
                'findings' => $findingCount,
                'opportunities' => $opportunityCount,
                'recommendations' => $recommendationCount,
                'ai_recommendations' => (int) ($aiResult['recommendations'] ?? 0),
                'completed_at' => now()->toIso8601String(),
            ];

            $summary['friction'] = $friction;
            $summary['cro_score'] = $overall;

            $crawl->forceFill([
                'cro_score' => $overall,
                'friction_score' => $friction['score'],
                'scoring_version' => config('cro.version'),
                'summary' => $summary,
            ])->save();
        });

        return [
            'findings' => $findingCount,
            'opportunities' => $opportunityCount,
            'recommendations' => $recommendationCount,
            'cro_score' => (float) ($crawl->fresh()->cro_score ?? 0),
            'friction_score' => (float) ($crawl->fresh()->friction_score ?? 0),
        ];
    }
}

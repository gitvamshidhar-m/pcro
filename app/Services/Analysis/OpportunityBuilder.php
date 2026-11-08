<?php

namespace App\Services\Analysis;

use App\Models\AuditFinding;
use App\Models\Crawl;
use App\Models\Opportunity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class OpportunityBuilder
{
    /**
     * @param  Collection<int, AuditFinding>  $findings
     * @return Collection<int, Opportunity>
     */
    public function build(Crawl $crawl, Collection $findings): Collection
    {
        Opportunity::where('crawl_id', $crawl->id)->delete();

        $maxOpportunities = (int) config('cro.opportunity.max_opportunities', 25);
        $scaleMax = (int) config('cro.opportunity.scale_max', 10);
        $minEffort = (int) config('cro.opportunity.min_effort', 1);

        $rows = [];

        foreach ($findings as $finding) {
            $rule = (array) (config("cro.categories.{$finding->category->value}.checks.{$finding->rule_key}") ?? []);
            $opportunity = (array) ($rule['opportunity'] ?? []);

            $impact = (int) ($opportunity['impact'] ?? 5);
            $floor = (int) (config("cro.opportunity.severity_impact_floor.{$finding->severity->value}") ?? 1);
            $impact = max($impact, $floor);

            $confidence = max(1, min($scaleMax, (int) round($finding->confidence / 10)));
            $reach = (int) ($opportunity['reach'] ?? 5);

            if ($finding->page !== null) {
                $pageReach = (int) (config("cro.opportunity.page_reach.{$finding->page->page_type->value}") ?? $reach);
                $reach = max($reach, $pageReach);
            }

            $funnel = (int) ($opportunity['funnel_relevance'] ?? ($opportunity['funnel'] ?? 5));
            $effort = max($minEffort, (int) ($opportunity['effort'] ?? 3));

            $impact = max(1, min($scaleMax, $impact));
            $reach = max(1, min($scaleMax, $reach));
            $funnel = max(1, min($scaleMax, $funnel));
            $effort = max($minEffort, min($scaleMax, $effort));

            $score = ($impact * $confidence * $reach * $funnel) / max($effort, $minEffort);

            $rows[] = [
                'ulid' => (string) Str::ulid(),
                'workspace_id' => $crawl->workspace_id,
                'crawl_id' => $crawl->id,
                'page_id' => $finding->page_id,
                'finding_id' => $finding->id,
                'title' => $finding->title,
                'description' => $finding->description,
                'category' => $finding->category->value,
                'impact' => $impact,
                'confidence' => $confidence,
                'reach' => $reach,
                'funnel_relevance' => $funnel,
                'effort' => $effort,
                'score' => round($score, 2),
                'evidence' => json_encode($finding->evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'heuristic' => json_encode([
                    'formula' => '(impact * confidence * reach * funnel_relevance) / effort',
                    'inputs' => [
                        'impact' => $impact,
                        'confidence' => $confidence,
                        'reach' => $reach,
                        'funnel_relevance' => $funnel,
                        'effort' => $effort,
                    ],
                    'source' => 'heuristic',
                    'rule_key' => $finding->rule_key,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'rank' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $rows = array_slice($rows, 0, $maxOpportunities);

        foreach (array_values($rows) as $index => $row) {
            $rows[$index]['rank'] = $index + 1;
        }

        if ($rows === []) {
            return collect();
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            Opportunity::insert($chunk);
        }

        return Opportunity::where('crawl_id', $crawl->id)->orderByDesc('score')->get();
    }
}

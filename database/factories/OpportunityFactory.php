<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\Opportunity;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'page_id' => null,
            'finding_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->sentence(18),
            'category' => 'conversion',
            'impact' => 8,
            'confidence' => 8,
            'reach' => 8,
            'funnel_relevance' => 8,
            'effort' => 3,
            'score' => 170.67,
            'evidence' => [],
            'heuristic' => [],
            'rank' => 1,
        ];
    }
}

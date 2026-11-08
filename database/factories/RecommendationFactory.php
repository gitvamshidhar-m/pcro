<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\Recommendation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'opportunity_id' => null,
            'ai_analysis_id' => null,
            'source' => 'heuristic',
            'title' => fake()->sentence(6),
            'description' => fake()->sentence(16),
            'rationale' => fake()->sentence(18),
            'action_steps' => ['Review the current element', 'Implement the change', 'Verify the result'],
            'ab_test' => [
                'hypothesis' => 'A clearer CTA will improve progression to contact.',
                'metric' => 'Contact form submissions',
            ],
            'priority' => 'high',
            'evidence_refs' => [],
            'is_validated' => true,
            'validation_notes' => 'Evidence references checked against stored findings.',
        ];
    }
}

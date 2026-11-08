<?php

namespace Database\Factories;

use App\Models\AiAnalysis;
use App\Models\Crawl;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAnalysis>
 */
class AiAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_version' => 'cro-analysis-v1',
            'input_hash' => hash('sha256', fake()->uuid()),
            'status' => 'completed',
            'request_meta' => [],
            'response' => [],
            'error' => null,
            'prompt_tokens' => 1200,
            'completion_tokens' => 600,
            'cost_usd' => 0.0005,
            'duration_ms' => 4200,
        ];
    }

    public function skipped(): static
    {
        return $this->state(fn (): array => [
            'status' => 'skipped',
            'error' => 'No AI API key configured; deterministic recommendations used.',
        ]);
    }
}

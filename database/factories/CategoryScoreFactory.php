<?php

namespace Database\Factories;

use App\Models\CategoryScore;
use App\Models\Crawl;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryScore>
 */
class CategoryScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'category' => 'ux',
            'score' => fake()->randomFloat(2, 40, 95),
            'weight' => 0.20,
            'components' => [],
            'rule_version' => config('cro.version'),
        ];
    }
}

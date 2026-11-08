<?php

namespace Database\Factories;

use App\Models\AuditRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditRule>
 */
class AuditRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rule_key' => 'test.'.fake()->unique()->slug(2, false),
            'category' => 'ux',
            'name' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'severity' => 'medium',
            'weight' => 1,
            'config' => ['confidence' => 80, 'opportunity' => ['impact' => 5, 'effort' => 3, 'reach' => 5, 'funnel' => 5]],
            'rule_version' => config('cro.version'),
            'is_active' => true,
        ];
    }
}

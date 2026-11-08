<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'website_id' => Website::factory(),
            'name' => fake()->company().' CRO project',
            'target_url' => 'https://example.com',
            'description' => null,
            'status' => 'active',
            'settings' => [],
        ];
    }
}

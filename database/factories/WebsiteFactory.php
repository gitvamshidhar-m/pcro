<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'workspace_id' => Workspace::factory(),
            'domain' => $domain,
            'url' => 'https://'.$domain,
            'label' => null,
            'favicon' => null,
            'metadata' => [],
        ];
    }
}

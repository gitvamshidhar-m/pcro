<?php

namespace Database\Factories;

use App\Models\AuditFinding;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditFinding>
 */
class AuditFindingFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'page_id' => Page::factory(),
            'rule_key' => 'conversion.primary_cta_present',
            'category' => 'conversion',
            'title' => $title,
            'description' => fake()->sentence(18),
            'severity' => 'high',
            'confidence' => 88,
            'evidence' => ['observed' => 'No primary CTA detected'],
            'recommended_improvement' => 'Add one clear primary call to action in the hero section.',
            'fingerprint' => hash('sha256', $title.Str::random(8)),
            'status' => 'open',
            'source' => 'rule',
        ];
    }
}

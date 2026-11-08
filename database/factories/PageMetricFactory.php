<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageMetric;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageMetric>
 */
class PageMetricFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'page_id' => Page::factory(),
            'lighthouse' => [
                'performance' => 78,
                'accessibility' => 88,
                'best_practices' => 90,
                'seo' => 92,
            ],
            'axe' => ['violations' => 2, 'critical' => 0, 'serious' => 1],
            'vitals' => ['lcp_ms' => 2800, 'cls' => 0.08, 'tbt_ms' => 310],
            'image_stats' => ['total' => 10, 'missing_alt' => 2, 'oversized' => 1],
            'js_errors' => [],
            'broken_links' => [],
            'availability' => ['lighthouse' => true, 'axe' => true, 'broken_links' => true],
            'captured_at' => now(),
        ];
    }
}

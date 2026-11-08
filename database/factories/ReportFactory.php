<?php

namespace Database\Factories;

use App\Models\Crawl;
use App\Models\Report;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'status' => 'completed',
            'disk_path' => 'reports/test.pdf',
            'generated_at' => now(),
            'meta' => [],
        ];
    }
}

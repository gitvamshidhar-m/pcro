<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function crawl(): Crawl
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create([
            'target_url' => 'https://example.com/',
        ]);

        $crawl = Crawl::factory()->for($workspace)->for($project)->completed()->create([
            'settings' => ['target_url' => 'https://example.com/'],
            'summary' => [
                'requested_url' => 'https://example.com/',
                'domain' => 'example.com',
                'has_ssl' => true,
                'security_headers' => [],
                'broken_links' => [],
                'mobile' => ['horizontal_overflow' => false, 'viewport_meta' => 'width=device-width,initial-scale=1', 'small_tap_targets' => 0],
                'lighthouse' => ['available' => true, 'categories' => ['performance' => 90, 'accessibility' => 95, 'best-practices' => 95, 'seo' => 85], 'audits' => []],
            ],
        ]);

        Page::factory()->for($workspace)->for($crawl)->home()->create([
            'extraction' => ['nav_links' => [], 'nav_count' => 0, 'has_nav' => false, 'has_header' => false, 'has_main' => false, 'has_footer' => false, 'h1_text' => null, 'h1_count' => 0, 'headings' => [], 'word_count' => 10, 'ctas' => [], 'primary_ctas' => 0, 'forms' => [], 'images' => [], 'image_count' => 0, 'images_missing_alt' => 0, 'testimonials' => [], 'trust_signals' => [], 'contact' => ['emails' => [], 'phones' => [], 'addresses' => []], 'privacy_links' => [], 'social_links' => [], 'axe' => ['available' => true, 'critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total_nodes' => 0], 'js_errors' => []],
        ]);

        app(AnalysisPipeline::class)->run($crawl);

        $user = User::factory()->create();
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->actingAs($user);

        return $crawl;
    }

    public function test_dashboard_and_subpages_render(): void
    {
        $crawl = $this->crawl();

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('analyses.dashboard', $crawl))->assertOk();
        $this->get(route('analyses.opportunities', $crawl))->assertOk();
        $this->get(route('analyses.recommendations', $crawl))->assertOk();
        $this->get(route('analyses.pages', $crawl))->assertOk();
        $this->get(route('analyses.friction', $crawl))->assertOk();
        $this->get(route('analyses.health', $crawl))->assertOk();
        $this->get(route('analyses.categories', $crawl))->assertOk();
        $this->get(route('analyses.history'))->assertOk();
        $this->get(route('analyses.report', $crawl))->assertOk();
    }

    public function test_recommendation_detail_renders(): void
    {
        $crawl = $this->crawl();

        $recommendation = $crawl->recommendations()->first();

        if ($recommendation) {
            $this->get(route('analyses.recommendation', [$crawl, $recommendation]))->assertOk();
        } else {
            $this->assertTrue(true);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AuditCategory;
use App\Jobs\AnalyzeCrawlJob;
use App\Models\CategoryScore;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Project;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function crawl(array $pageExtraction = []): Crawl
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create(['domain' => 'example.com']);
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
                'mobile' => [
                    'horizontal_overflow' => false,
                    'viewport_meta' => 'width=device-width,initial-scale=1',
                    'small_tap_targets' => 0,
                ],
                'lighthouse' => [
                    'available' => true,
                    'categories' => ['performance' => 92, 'accessibility' => 98, 'best-practices' => 95, 'seo' => 90],
                    'audits' => [
                        'largest-contentful-paint' => ['numeric_value' => 2100],
                        'cumulative-layout-shift' => ['numeric_value' => 0.05],
                        'total-blocking-time' => ['numeric_value' => 120],
                    ],
                ],
            ],
        ]);

        Page::factory()->for($workspace)->for($crawl)->home()->create([
            'extraction' => array_merge([
                'nav_links' => [
                    ['text' => 'Pricing', 'href' => '/pricing'],
                    ['text' => 'Contact', 'href' => '/contact'],
                    ['text' => 'About', 'href' => '/about'],
                ],
                'nav_count' => 3,
                'has_nav' => true,
                'has_header' => true,
                'has_main' => true,
                'has_footer' => true,
                'h1_text' => 'Grow revenue with predictive CRO',
                'h1_count' => 1,
                'headings' => [['level' => 1, 'text' => 'Grow revenue'], ['level' => 2, 'text' => 'Benefits']],
                'word_count' => 420,
                'avg_sentence_words' => 14,
                'reading_ease' => 62,
                'intro_text' => 'We help SaaS teams increase conversion rates without guessing.',
                'main_text' => 'We help SaaS teams increase conversion rates. Save time. Grow revenue. Trusted by 120 customers.',
                'ctas' => [
                    ['text' => 'Book a 20-minute demo', 'tag' => 'a', 'above_fold' => true, 'is_primary' => true],
                ],
                'primary_ctas' => 1,
                'above_fold_ctas' => 1,
                'forms' => [
                    ['field_count' => 3, 'has_email' => true, 'has_message' => true, 'above_fold' => false],
                ],
                'images' => [['has_alt' => true, 'visible' => true]],
                'image_count' => 1,
                'images_missing_alt' => 0,
                'testimonials' => [['text' => 'Great product.', 'author' => 'Jane Doe']],
                'trust_signals' => [
                    ['key' => 'trusted', 'label' => 'Trusted by 120 customers'],
                    ['key' => 'guarantee', 'label' => '30-day money back guarantee'],
                ],
                'contact' => ['emails' => ['hello@example.com'], 'phones' => ['+1 555 0100'], 'addresses' => []],
                'privacy_links' => [['text' => 'Privacy policy', 'href' => '/privacy']],
                'social_links' => [['network' => 'linkedin', 'href' => 'https://linkedin.com/company/example']],
                'axe' => ['available' => true, 'critical' => 0, 'serious' => 0, 'moderate' => 1, 'minor' => 0, 'total_nodes' => 1],
                'js_errors' => [],
            ], $pageExtraction),
        ]);

        Page::factory()->for($workspace)->for($crawl)->create([
            'page_type' => \App\Enums\PageType::Pricing,
            'path' => '/pricing',
            'url' => 'https://example.com/pricing',
            'url_hash' => hash('sha256', 'https://example.com/pricing'),
            'depth' => 1,
            'title' => 'Pricing',
            'word_count' => 300,
            'extraction' => [
                'h1_text' => 'Simple pricing',
                'h1_count' => 1,
                'headings' => [['level' => 1, 'text' => 'Simple pricing']],
                'word_count' => 300,
                'ctas' => [['text' => 'Start free trial', 'tag' => 'a', 'above_fold' => true, 'is_primary' => true]],
                'primary_ctas' => 1,
                'forms' => [],
            ],
        ]);

        return $crawl;
    }

    public function test_pipeline_scores_every_category_and_persists_findings(): void
    {
        $crawl = $this->crawl();

        $result = app(AnalysisPipeline::class)->run($crawl);

        $crawl->refresh();

        $this->assertGreaterThanOrEqual(0, $result['findings']);
        $this->assertGreaterThan(0, CategoryScore::where('crawl_id', $crawl->id)->count());
        $this->assertCount(5, CategoryScore::where('crawl_id', $crawl->id)->get());

        foreach (AuditCategory::all() as $category) {
            $score = CategoryScore::where('crawl_id', $crawl->id)->where('category', $category->value)->first();
            $this->assertNotNull($score, "Missing score for {$category->value}");
            $this->assertGreaterThanOrEqual(0, $score->score);
            $this->assertLessThanOrEqual(100, $score->score);
            $this->assertNotEmpty($score->components);
        }

        $this->assertNotNull($crawl->cro_score);
        $this->assertNotNull($crawl->friction_score);
        $this->assertSame(config('cro.version'), $crawl->scoring_version);
        $this->assertSame('completed', data_get($crawl->summary, 'analysis.status'));

        $configKeys = collect(config('cro.categories'))->flatMap(fn (array $category): array => array_keys($category['checks']))->all();
        $componentKeys = CategoryScore::where('crawl_id', $crawl->id)
            ->get()
            ->flatMap(fn (CategoryScore $score): array => array_column($score->components, 'rule_key'))
            ->all();

        foreach ($configKeys as $key) {
            $this->assertContains($key, $componentKeys, "Check {$key} was not assessed.");
        }
    }

    public function test_pipeline_creates_opportunities_and_recommendations_for_issues(): void
    {
        $crawl = $this->crawl();

        app(AnalysisPipeline::class)->run($crawl);

        $this->assertDatabaseHas('opportunities', ['crawl_id' => $crawl->id]);
        $this->assertDatabaseHas('recommendations', ['crawl_id' => $crawl->id, 'source' => 'heuristic']);

        $opportunity = \App\Models\Opportunity::where('crawl_id', $crawl->id)->orderByDesc('score')->first();
        $this->assertNotNull($opportunity);
        $this->assertGreaterThan(0, $opportunity->score);
        $this->assertNotNull($opportunity->heuristic);
    }

    public function test_analyze_crawl_job_runs_the_pipeline(): void
    {
        $crawl = $this->crawl();

        (new AnalyzeCrawlJob($crawl))->handle(app(AnalysisPipeline::class));

        $crawl->refresh();

        $this->assertSame('completed', $crawl->stage);
        $this->assertSame(100, $crawl->progress);
        $this->assertNotNull($crawl->cro_score);
    }

    public function test_pipeline_is_idempotent(): void
    {
        $crawl = $this->crawl();

        app(AnalysisPipeline::class)->run($crawl);
        $firstScore = $crawl->fresh()->cro_score;
        $firstFindings = \App\Models\AuditFinding::where('crawl_id', $crawl->id)->count();

        app(AnalysisPipeline::class)->run($crawl->fresh());

        $this->assertSame($firstScore, $crawl->fresh()->cro_score);
        $this->assertSame($firstFindings, \App\Models\AuditFinding::where('crawl_id', $crawl->id)->count());
    }
}

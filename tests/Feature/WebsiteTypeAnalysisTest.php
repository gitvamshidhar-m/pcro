<?php

namespace Tests\Feature;

use App\Models\CategoryScore;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Project;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Analysis\AnalysisPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteTypeAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function websiteTypes(): array
    {
        $base = [
            'nav_links' => [['text' => 'Pricing', 'href' => '/pricing'], ['text' => 'Contact', 'href' => '/contact']],
            'nav_count' => 2,
            'has_nav' => true,
            'has_header' => true,
            'has_main' => true,
            'has_footer' => true,
            'h1_text' => 'Welcome',
            'h1_count' => 1,
            'headings' => [['level' => 1, 'text' => 'Welcome']],
            'word_count' => 300,
            'avg_sentence_words' => 16,
            'reading_ease' => 58,
            'intro_text' => 'We help teams grow.',
            'main_text' => 'We help teams grow. Trusted by customers. Contact us today.',
            'ctas' => [['text' => 'Get started', 'tag' => 'a', 'above_fold' => true, 'is_primary' => true]],
            'primary_ctas' => 1,
            'above_fold_ctas' => 1,
            'forms' => [['field_count' => 3, 'has_email' => true, 'has_message' => true, 'above_fold' => false]],
            'images' => [['has_alt' => true, 'visible' => true]],
            'image_count' => 1,
            'images_missing_alt' => 0,
            'testimonials' => [['text' => 'Great.', 'author' => 'Jane']],
            'trust_signals' => [['key' => 'trusted', 'label' => 'Trusted by customers']],
            'contact' => ['emails' => ['hello@example.com'], 'phones' => [], 'addresses' => []],
            'privacy_links' => [['text' => 'Privacy', 'href' => '/privacy']],
            'social_links' => [],
            'axe' => ['available' => true, 'critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0, 'total_nodes' => 0],
            'js_errors' => [],
        ];

        return [
            'ecommerce' => ['ecommerce', array_merge($base, ['h1_text' => 'Shop our products', 'main_text' => 'Buy products online. Free shipping. 30-day returns.'])],
            'saas' => ['saas', array_merge($base, ['h1_text' => 'The platform for modern teams', 'main_text' => 'Increase productivity. Start free trial.'])],
            'local-service' => ['local-service', array_merge($base, ['h1_text' => 'Your local plumber', 'main_text' => 'Fast reliable service. Call now.'])],
            'blog' => ['blog', array_merge($base, ['h1_text' => 'Latest insights', 'main_text' => 'Read our latest articles and guides.'])],
            'landing' => ['landing', array_merge($base, ['h1_text' => 'Get the free guide', 'main_text' => 'Download the checklist. Limited offer.'])],
            'corporate' => ['corporate', array_merge($base, ['h1_text' => 'About our company', 'main_text' => 'We are a global enterprise. Contact our team.'])],
        ];
    }

    #[DataProvider('websiteTypes')]
    public function test_pipeline_runs_for_each_website_type(string $type, array $extraction): void
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
                'lighthouse' => ['available' => true, 'categories' => ['performance' => 85, 'accessibility' => 90, 'best-practices' => 90, 'seo' => 80], 'audits' => []],
            ],
        ]);

        Page::factory()->for($workspace)->for($crawl)->home()->create([
            'extraction' => $extraction,
        ]);

        app(AnalysisPipeline::class)->run($crawl);

        $crawl->refresh();

        $this->assertSame(config('cro.version'), $crawl->scoring_version);
        $this->assertNotNull($crawl->cro_score);
        $this->assertGreaterThan(0, CategoryScore::where('crawl_id', $crawl->id)->count());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PageType;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\PageElement;
use App\Models\PageMetric;
use App\Models\Project;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Crawler\CrawlResultPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrawlResultPersisterTest extends TestCase
{
    use RefreshDatabase;

    private function crawl(): Crawl
    {
        $workspace = Workspace::factory()->create();
        $website = Website::factory()->for($workspace)->create(['domain' => 'example.com']);
        $project = Project::factory()->for($workspace)->for($website)->create([
            'target_url' => 'https://example.com/',
        ]);

        return Crawl::factory()->for($workspace)->for($project)->create([
            'settings' => ['target_url' => 'https://example.com/'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'ok' => true,
            'schema_version' => 1,
            'crawl' => [
                'site_name' => 'Example company',
                'domain' => 'example.com',
                'entry_url' => 'https://example.com/',
                'final_url' => 'https://example.com/',
                'title' => 'Example company',
                'meta_description' => 'We help teams convert more visitors.',
                'lang' => 'en',
                'has_ssl' => true,
                'security_headers' => ['strict-transport-security' => true],
                'robots' => ['found' => true, 'sitemaps' => ['https://example.com/sitemap.xml']],
                'mobile' => [
                    'horizontal_overflow' => false,
                    'viewport_meta' => 'width=device-width,initial-scale=1',
                    'screenshot' => 'screenshots/mobile-home.jpg',
                ],
                'lighthouse' => [
                    'available' => true,
                    'categories' => [
                        'performance' => 88,
                        'accessibility' => 100,
                        'best-practices' => 100,
                        'seo' => 80,
                    ],
                    'audits' => [
                        'largest-contentful-paint' => ['numeric_value' => 1400],
                        'cumulative-layout-shift' => ['numeric_value' => 0.02],
                        'total-blocking-time' => ['numeric_value' => 40],
                    ],
                ],
                'broken_links' => [],
                'blocked_requests' => [],
            ],
            'stats' => [
                'pages_discovered' => 2,
                'pages_crawled' => 2,
                'pages_failed' => 1,
                'robots_blocked' => 0,
                'duration_ms' => 12345,
            ],
            'pages' => [
                [
                    'url' => 'https://example.com/',
                    'final_url' => 'https://example.com/',
                    'requested_url' => 'https://example.com/',
                    'depth' => 0,
                    'status_code' => 200,
                    'title' => 'Example company',
                    'meta_description' => 'We help teams convert more visitors.',
                    'h1_text' => 'Convert more of your visitors',
                    'h1_texts' => ['Convert more of your visitors'],
                    'h1_count' => 1,
                    'word_count' => 420,
                    'main_text' => 'Long homepage copy.',
                    'screenshot' => [
                        'full' => 'screenshots/001-home-full.jpg',
                        'viewport' => 'screenshots/001-home.jpg',
                    ],
                    'ctas' => [
                        [
                            'text' => 'Start free trial',
                            'tag' => 'a',
                            'href' => 'https://example.com/signup',
                            'above_fold' => true,
                            'is_primary' => true,
                            'is_submit' => false,
                        ],
                    ],
                    'forms' => [
                        [
                            'selector' => 'form#lead',
                            'action' => '/lead',
                            'method' => 'post',
                            'field_count' => 3,
                            'has_email' => true,
                            'has_phone' => false,
                            'has_message' => true,
                            'above_fold' => false,
                            'visible' => true,
                            'fields' => [
                                ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => 'Work email'],
                            ],
                        ],
                    ],
                    'images' => [
                        ['src' => '/hero.jpg', 'alt' => 'Dashboard screenshot', 'has_alt' => true, 'visible' => true, 'above_fold' => true],
                    ],
                    'image_count' => 1,
                    'images_missing_alt' => 0,
                    'testimonials' => [
                        ['text' => 'Great product.', 'author' => 'Jane', 'rating_text' => '5/5'],
                    ],
                    'trust_signals' => [
                        ['key' => 'money_back', 'label' => '30-day money back guarantee', 'match' => 'money back'],
                    ],
                    'contact' => [
                        'emails' => ['hello@example.com'],
                        'phones' => ['+1 555 0100'],
                        'addresses' => ['1 Example Street'],
                    ],
                    'privacy_links' => [
                        ['text' => 'Privacy policy', 'href' => '/privacy'],
                    ],
                    'social_links' => [
                        ['network' => 'linkedin', 'href' => 'https://linkedin.com/company/example'],
                    ],
                    'nav_links' => [
                        ['text' => 'Pricing', 'href' => '/pricing'],
                    ],
                    'axe' => [
                        'available' => true,
                        'violations' => 2,
                        'critical' => 0,
                        'serious' => 1,
                        'moderate' => 1,
                        'minor' => 0,
                        'total_nodes' => 3,
                        'items' => [],
                    ],
                ],
                [
                    'url' => 'https://example.com/pricing',
                    'final_url' => 'https://example.com/pricing',
                    'requested_url' => 'https://example.com/pricing',
                    'depth' => 1,
                    'status_code' => 200,
                    'title' => 'Pricing',
                    'h1_text' => 'Simple pricing',
                    'word_count' => 210,
                    'screenshot' => 'screenshots/002-pricing.jpg',
                ],
            ],
            'failures' => [
                ['url' => 'https://example.com/broken', 'error' => 'Navigation timeout'],
            ],
        ];
    }

    public function test_it_persists_pages_elements_metrics_and_the_crawl_summary(): void
    {
        $crawl = $this->crawl();

        app(CrawlResultPersister::class)->persist($crawl, $this->payload());

        $crawl->refresh();

        $this->assertSame(2, $crawl->pages_crawled);
        $this->assertSame(2, $crawl->pages_discovered);
        $this->assertSame(1, $crawl->pages_failed);
        $this->assertSame('crawls/'.$crawl->ulid, $crawl->worker_output_path);
        $this->assertSame(12345, $crawl->duration_ms);
        $this->assertSame('example.com', data_get($crawl->summary, 'domain'));
        $this->assertTrue(data_get($crawl->summary, 'lighthouse_available'));
        $this->assertSame(0, data_get($crawl->summary, 'broken_links'));

        $pages = Page::orderBy('depth')->get();

        $this->assertCount(2, $pages);
        $this->assertSame(PageType::Home, $pages[0]->page_type);
        $this->assertSame(PageType::Pricing, $pages[1]->page_type);
        $this->assertSame('crawls/'.$crawl->ulid.'/screenshots/001-home-full.jpg', $pages[0]->screenshot_path);
        $this->assertSame('crawls/'.$crawl->ulid.'/screenshots/002-pricing.jpg', $pages[1]->screenshot_path);
        $this->assertSame('Convert more of your visitors', $pages[0]->h1);

        $home = $pages[0];
        $this->assertSame(1, PageElement::where('page_id', $home->id)->where('type', 'cta')->count());
        $this->assertSame(1, PageElement::where('page_id', $home->id)->where('type', 'form')->count());
        $this->assertSame(1, PageElement::where('page_id', $home->id)->where('type', 'form_field')->count());
        $this->assertSame(1, PageElement::where('page_id', $home->id)->where('type', 'image')->count());
        $this->assertSame(1, PageElement::where('page_id', $home->id)->where('type', 'testimonial')->count());
        $this->assertSame(1, PageElement::where('page_id', $home->id)->where('type', 'trust_signal')->count());
        $this->assertSame(3, PageElement::where('page_id', $home->id)->where('type', 'contact')->count());

        $homeMetric = PageMetric::where('page_id', $home->id)->firstOrFail();
        $this->assertTrue($homeMetric->availability['lighthouse']);
        $this->assertEquals(1400.0, $homeMetric->vitals['lcp_ms']);
        $this->assertSame(88, $homeMetric->vitals['performance_score']);
        $this->assertTrue($homeMetric->availability['axe']);

        $pricingMetric = PageMetric::where('page_id', $pages[1]->id)->firstOrFail();
        $this->assertNull($pricingMetric->lighthouse);
        $this->assertFalse($pricingMetric->availability['lighthouse']);
    }

    public function test_repeated_persistence_replaces_previous_pages_without_orphans(): void
    {
        $crawl = $this->crawl();
        $persister = app(CrawlResultPersister::class);

        $persister->persist($crawl, $this->payload());
        $persister->persist($crawl, $this->payload());

        $this->assertSame(2, Page::where('crawl_id', $crawl->id)->count());
        $this->assertSame(2, PageMetric::count());

        // Elements are cascade-deleted with their pages.
        $pageIds = Page::pluck('id');
        $this->assertSame(
            PageElement::whereIn('page_id', $pageIds)->count(),
            PageElement::count(),
        );
    }
}

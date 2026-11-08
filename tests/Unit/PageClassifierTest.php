<?php

namespace Tests\Unit;

use App\Enums\PageType;
use App\Services\Crawler\PageClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageClassifierTest extends TestCase
{
    private PageClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new PageClassifier;
    }

    /**
     * @return array<string, array{0: string, 1: PageType}>
     */
    public static function urlProvider(): array
    {
        return [
            'root url is the homepage' => ['https://example.com/', PageType::Home],
            'empty path is the homepage' => ['https://example.com', PageType::Home],
            'pricing page' => ['https://example.com/pricing', PageType::Pricing],
            'plans page' => ['https://example.com/plans-and-packages', PageType::Pricing],
            'contact page' => ['https://example.com/contact-us', PageType::Contact],
            'book a call page' => ['https://example.com/book-a-call', PageType::Contact],
            'privacy page' => ['https://example.com/privacy-policy', PageType::Legal],
            'terms page' => ['https://example.com/terms-and-conditions', PageType::Legal],
            'blog index' => ['https://example.com/blog', PageType::Blog],
            'case study article' => ['https://example.com/insights/how-we-grew', PageType::Blog],
            'about page' => ['https://example.com/about-us', PageType::About],
            'team page' => ['https://example.com/our-team', PageType::About],
            'product page' => ['https://example.com/shop/widget', PageType::Product],
            'service page' => ['https://example.com/services/consulting', PageType::Service],
            'landing page' => ['https://example.com/free-trial', PageType::Landing],
            'unknown page' => ['https://example.com/xyzzy', PageType::Other],
        ];
    }

    #[DataProvider('urlProvider')]
    public function test_it_classifies_pages_from_the_url(string $url, PageType $expected): void
    {
        $this->assertSame($expected, $this->classifier->classify($url, ['depth' => 1]));
    }

    public function test_a_root_url_is_always_the_homepage(): void
    {
        $this->assertSame(
            PageType::Home,
            $this->classifier->classify('https://example.com/', ['depth' => 1, 'title' => 'Pricing and plans']),
        );
    }

    public function test_depth_zero_is_treated_as_the_homepage(): void
    {
        $this->assertSame(
            PageType::Home,
            $this->classifier->classify('https://example.com/landing', ['depth' => 0]),
        );
    }

    public function test_it_uses_title_and_h1_signals_when_the_url_is_neutral(): void
    {
        $this->assertSame(
            PageType::Pricing,
            $this->classifier->classify('https://example.com/solutions', ['depth' => 1, 'title' => 'Simple pricing for teams']),
        );

        $this->assertSame(
            PageType::Contact,
            $this->classifier->classify('https://example.com/hello', ['depth' => 1, 'h1_text' => 'Talk to our team']),
        );

        $this->assertSame(
            PageType::Landing,
            $this->classifier->classify('https://example.com/spring', ['depth' => 1, 'title' => 'Spring campaign offer']),
        );
    }

    public function test_legal_pages_win_over_other_keywords(): void
    {
        $this->assertSame(
            PageType::Legal,
            $this->classifier->classify('https://example.com/blog/privacy-policy'),
        );
    }

    public function test_it_handles_urls_without_a_scheme(): void
    {
        $this->assertSame(PageType::Pricing, $this->classifier->classify('/pricing', ['depth' => 1]));
    }
}

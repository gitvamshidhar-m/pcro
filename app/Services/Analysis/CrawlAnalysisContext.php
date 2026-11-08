<?php

namespace App\Services\Analysis;

use App\Models\Crawl;
use App\Models\Page;
use App\Models\PageMetric;
use Illuminate\Support\Collection;

final class CrawlAnalysisContext
{
    /**
     * @var Collection<int, Page>
     */
    public readonly Collection $pages;

    public readonly ?Page $homePage;

    public function __construct(
        public readonly Crawl $crawl,
        Collection $pages,
    ) {
        $this->pages = $pages->values();
        $this->homePage = $pages->firstWhere('page_type', 'home')
            ?? $pages->firstWhere('depth', 0)
            ?? $pages->first();
    }

    public static function from(Crawl $crawl): self
    {
        $crawl->loadMissing(['project.website', 'pages.elements', 'pages.metrics']);

        return new self($crawl, $crawl->pages);
    }

    /**
     * @return Collection<int, Page>
     */
    public function conversionPages(): Collection
    {
        return $this->pages->filter(fn (Page $page): bool => in_array(
            $page->page_type->value,
            ['home', 'product', 'service', 'pricing', 'contact', 'landing'],
            true,
        ))->values();
    }

    /**
     * @return Collection<int, Page>
     */
    public function pagesOfType(string $type): Collection
    {
        return $this->pages->where('page_type', $type)->values();
    }

    public function summary(): array
    {
        $summary = $this->crawl->summary;

        return is_array($summary) ? $summary : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function extraction(Page $page): array
    {
        $extraction = $page->extraction;

        return is_array($extraction) ? $extraction : [];
    }

    public function homeMetric(): ?PageMetric
    {
        return $this->homePage?->metrics;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lighthouse(): ?array
    {
        $lighthouse = $this->homeMetric()?->lighthouse;

        return is_array($lighthouse) ? $lighthouse : null;
    }

    public function lighthouseAvailable(): bool
    {
        return (bool) data_get($this->lighthouse(), 'available', false);
    }

    public function mobileAvailable(): bool
    {
        return is_array($this->summary()['mobile'] ?? null);
    }

    public function mobile(): array
    {
        $mobile = $this->summary()['mobile'] ?? [];

        return is_array($mobile) ? $mobile : [];
    }

    public function axeAvailable(): bool
    {
        return $this->pages->contains(fn (Page $page): bool => (bool) data_get($page->metrics?->axe, 'available', false));
    }

    public function inspectedCount(): int
    {
        return $this->pages->count();
    }

    /**
     * @return array<int, string>
     */
    public function pageUrls(?Collection $pages = null, int $limit = 10): array
    {
        return ($pages ?? $this->pages)
            ->pluck('url')
            ->filter(fn ($url): bool => is_string($url) && $url !== '')
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    public function homeUrl(): ?string
    {
        return $this->homePage?->url;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function brokenLinks(): array
    {
        $broken = $this->summary()['broken_links'] ?? [];

        return is_array($broken) ? $broken : [];
    }

    public function hasSsl(): bool
    {
        return (bool) ($this->summary()['has_ssl'] ?? false);
    }

    public function privacyLinks(): int
    {
        return $this->pages->sum(fn (Page $page): int => count($this->extraction($page)['privacy_links'] ?? []));
    }

    public function securityHeaders(): array
    {
        $headers = $this->summary()['security_headers'] ?? [];

        return is_array($headers) ? $headers : [];
    }
}

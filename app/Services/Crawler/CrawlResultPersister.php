<?php

namespace App\Services\Crawler;

use App\Models\Crawl;
use App\Models\Page;
use App\Models\PageElement;
use App\Models\PageMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrawlResultPersister
{
    public function __construct(
        private readonly PageClassifier $classifier,
        private readonly CrawlJobBuilder $jobBuilder,
    ) {}

    public function persist(Crawl $crawl, array $payload): void
    {
        $site = $payload['crawl'] ?? [];
        $pages = $payload['pages'] ?? [];
        $stats = $payload['stats'] ?? [];

        DB::transaction(function () use ($crawl, $site, $pages, $stats): void {
            Page::where('crawl_id', $crawl->id)->delete();

            $relativeRoot = $this->jobBuilder->relativeOutputDirectory($crawl);
            $homePageId = null;

            foreach ($pages as $index => $pageData) {
                $page = $this->storePage($crawl, $pageData, $index, $relativeRoot);

                if ($homePageId === null && ((int) ($pageData['depth'] ?? 1) === 0 || $page->page_type->value === 'home')) {
                    $homePageId = $page->id;
                }

                $this->storeElements($crawl, $page, $pageData);
                $this->storeMetrics($crawl, $page, $pageData, $site, $page->id === $homePageId);
            }

            $crawl->forceFill([
                'pages_discovered' => (int) ($stats['pages_discovered'] ?? count($pages)),
                'pages_crawled' => (int) ($stats['pages_crawled'] ?? count($pages)),
                'pages_failed' => (int) ($stats['pages_failed'] ?? count($payload['failures'] ?? [])),
                'worker_output_path' => $relativeRoot,
                'duration_ms' => (int) ($stats['duration_ms'] ?? 0),
                'summary' => $this->summary($site, $stats, $pages, $payload['failures'] ?? []),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $pageData
     */
    private function storePage(Crawl $crawl, array $pageData, int $index, string $relativeRoot): Page
    {
        $url = (string) ($pageData['final_url'] ?? $pageData['url'] ?? '');
        $requestedUrl = (string) ($pageData['requested_url'] ?? $pageData['url'] ?? $url);
        $path = parse_url($url, PHP_URL_PATH);
        $pageType = $this->classifier->classify($requestedUrl, $pageData);

        return Page::create([
            'workspace_id' => $crawl->workspace_id,
            'crawl_id' => $crawl->id,
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'path' => is_string($path) && $path !== '' ? $path : '/',
            'page_type' => $pageType,
            'depth' => (int) ($pageData['depth'] ?? 0),
            'status_code' => isset($pageData['status_code']) ? (int) $pageData['status_code'] : null,
            'title' => isset($pageData['title']) ? mb_substr((string) $pageData['title'], 0, 500) : null,
            'meta_description' => isset($pageData['meta_description']) ? mb_substr((string) $pageData['meta_description'], 0, 2000) : null,
            'canonical_url' => $pageData['canonical_url'] ?? null,
            'h1' => isset($pageData['h1_text']) ? mb_substr((string) $pageData['h1_text'], 0, 1000) : null,
            'word_count' => (int) ($pageData['word_count'] ?? 0),
            'content_hash' => hash('sha256', (string) ($pageData['main_text'] ?? $pageData['body_text'] ?? $url)),
            'is_rendered' => true,
            'extraction' => $this->extractionSnapshot($pageData, $index),
            'screenshot_path' => $this->screenshotPath($pageData, $relativeRoot),
        ]);
    }

    /**
     * @param  array<string, mixed>  $pageData
     * @return array<string, mixed>
     */
    private function extractionSnapshot(array $pageData, int $index): array
    {
        $keys = [
            'og', 'twitter', 'headings', 'h1_count', 'h1_texts', 'nav_links', 'nav_count',
            'links', 'buttons', 'ctas', 'above_fold_ctas', 'primary_ctas', 'forms', 'form_count',
            'images', 'image_count', 'images_missing_alt', 'lazy_images', 'testimonials',
            'review_count_on_page', 'aggregate_rating', 'structured_types', 'structured_name',
            'contact', 'social_links', 'trust_signals', 'privacy_links', 'consent_banner',
            'main_text', 'intro_text', 'body_text', 'sentence_count', 'avg_sentence_words',
            'reading_ease', 'long_word_ratio', 'paragraph_count', 'section_count', 'has_header',
            'has_main', 'has_footer', 'has_nav', 'has_sidebar', 'horizontal_overflow',
            'small_tap_targets', 'password_fields', 'resource_count', 'transfer_size_bytes',
            'dom_content_loaded_ms', 'load_ms', 'html_size_bytes', 'page_height', 'lang',
            'viewport_meta', 'theme_color', 'favicon', 'robots_meta', 'final_url', 'depth',
            'priority', 'response_headers', 'js_errors',
        ];

        $snapshot = ['index' => $index];

        foreach ($keys as $key) {
            if (array_key_exists($key, $pageData)) {
                $snapshot[$key] = $pageData[$key];
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $pageData
     */
    private function screenshotPath(array $pageData, string $relativeRoot): ?string
    {
        $screenshot = $pageData['screenshot'] ?? null;

        if (is_array($screenshot)) {
            $candidate = $screenshot['full'] ?? $screenshot['viewport'] ?? null;
        } else {
            $candidate = $screenshot;
        }

        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return $relativeRoot.'/'.ltrim(str_replace('\\', '/', $candidate), '/');
    }

    /**
     * @param  array<string, mixed>  $pageData
     */
    private function storeElements(Crawl $crawl, Page $page, array $pageData): void
    {
        $rows = [];
        $position = 0;
        $now = now();

        $push = function (string $type, ?string $text, ?string $tag = null, ?string $selector = null, array $attributes = []) use (&$rows, &$position, $crawl, $page, $now): void {
            $rows[] = [
                'ulid' => (string) Str::ulid(),
                'workspace_id' => $crawl->workspace_id,
                'page_id' => $page->id,
                'type' => $type,
                'tag' => $tag,
                'text' => $text !== null ? mb_substr($text, 0, 2000) : null,
                'selector' => $selector !== null ? mb_substr($selector, 0, 500) : null,
                'attributes' => $attributes === [] ? null : json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'position' => $position++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        foreach (($pageData['headings'] ?? []) as $heading) {
            $push('heading', (string) ($heading['text'] ?? ''), 'h'.($heading['level'] ?? 2), $heading['selector'] ?? null, [
                'level' => $heading['level'] ?? null,
            ]);
        }

        foreach (($pageData['nav_links'] ?? []) as $link) {
            $push('nav_link', (string) ($link['text'] ?? ''), 'a', null, [
                'href' => $link['href'] ?? null,
            ]);
        }

        foreach (($pageData['ctas'] ?? []) as $cta) {
            $push('cta', (string) ($cta['text'] ?? ''), $cta['tag'] ?? null, $cta['selector'] ?? null, [
                'href' => $cta['href'] ?? null,
                'above_fold' => (bool) ($cta['above_fold'] ?? false),
                'is_primary' => (bool) ($cta['is_primary'] ?? false),
                'is_submit' => (bool) ($cta['is_submit'] ?? false),
                'background' => $cta['background'] ?? null,
            ]);
        }

        foreach (($pageData['forms'] ?? []) as $formIndex => $form) {
            $push('form', (string) ($form['submit_text'] ?? 'Form'), 'form', $form['selector'] ?? null, [
                'action' => $form['action'] ?? null,
                'method' => $form['method'] ?? null,
                'field_count' => (int) ($form['field_count'] ?? 0),
                'has_email' => (bool) ($form['has_email'] ?? false),
                'has_phone' => (bool) ($form['has_phone'] ?? false),
                'has_message' => (bool) ($form['has_message'] ?? false),
                'above_fold' => (bool) ($form['above_fold'] ?? false),
                'visible' => (bool) ($form['visible'] ?? true),
            ]);

            foreach (($form['fields'] ?? []) as $field) {
                $push('form_field', (string) ($field['label'] ?? $field['name'] ?? $field['type'] ?? 'Field'), $field['type'] ?? 'input', null, [
                    'form_index' => $formIndex,
                    'name' => $field['name'] ?? null,
                    'type' => $field['type'] ?? null,
                    'required' => (bool) ($field['required'] ?? false),
                    'autocomplete' => $field['autocomplete'] ?? null,
                ]);
            }
        }

        foreach (($pageData['images'] ?? []) as $image) {
            $push('image', (string) ($image['alt'] ?? ''), 'img', null, [
                'src' => $image['src'] ?? null,
                'has_alt' => (bool) ($image['has_alt'] ?? false),
                'visible' => (bool) ($image['visible'] ?? false),
                'above_fold' => (bool) ($image['above_fold'] ?? false),
                'loading' => $image['loading'] ?? null,
                'natural_width' => $image['natural_width'] ?? null,
                'natural_height' => $image['natural_height'] ?? null,
            ]);
        }

        foreach (($pageData['testimonials'] ?? []) as $testimonial) {
            $push('testimonial', (string) ($testimonial['text'] ?? ''), 'blockquote', $testimonial['selector'] ?? null, [
                'author' => $testimonial['author'] ?? null,
                'rating_text' => $testimonial['rating_text'] ?? null,
            ]);
        }

        foreach (($pageData['trust_signals'] ?? []) as $signal) {
            $push('trust_signal', (string) ($signal['label'] ?? $signal['match'] ?? ''), null, null, [
                'key' => $signal['key'] ?? null,
                'match' => $signal['match'] ?? null,
                'context' => $signal['context'] ?? null,
            ]);
        }

        foreach (($pageData['social_links'] ?? []) as $link) {
            $push('social_link', (string) ($link['network'] ?? ''), 'a', null, ['href' => $link['href'] ?? null]);
        }

        foreach (($pageData['privacy_links'] ?? []) as $link) {
            $push('privacy_link', (string) ($link['text'] ?? ''), 'a', null, ['href' => $link['href'] ?? null]);
        }

        $contact = $pageData['contact'] ?? [];

        foreach (($contact['emails'] ?? []) as $email) {
            $push('contact', (string) $email, null, null, ['channel' => 'email']);
        }

        foreach (($contact['phones'] ?? []) as $phone) {
            $push('contact', (string) $phone, null, null, ['channel' => 'phone']);
        }

        foreach (($contact['addresses'] ?? []) as $address) {
            $push('contact', (string) $address, 'address', null, ['channel' => 'address']);
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            PageElement::insert($chunk);
        }
    }

    /**
     * @param  array<string, mixed>  $pageData
     * @param  array<string, mixed>  $site
     */
    private function storeMetrics(Crawl $crawl, Page $page, array $pageData, array $site, bool $isHome): void
    {
        $lighthouse = $isHome ? ($site['lighthouse'] ?? null) : null;
        $axe = is_array($pageData['axe'] ?? null) ? $pageData['axe'] : null;

        PageMetric::create([
            'workspace_id' => $crawl->workspace_id,
            'page_id' => $page->id,
            'lighthouse' => $lighthouse,
            'axe' => $axe,
            'vitals' => $this->vitals($lighthouse),
            'image_stats' => [
                'total' => (int) ($pageData['image_count'] ?? 0),
                'missing_alt' => (int) ($pageData['images_missing_alt'] ?? 0),
                'lazy' => (int) ($pageData['lazy_images'] ?? 0),
                'tracked' => count($pageData['images'] ?? []),
            ],
            'js_errors' => $pageData['js_errors'] ?? [],
            'broken_links' => $isHome ? ($site['broken_links'] ?? []) : [],
            'availability' => [
                'lighthouse' => is_array($lighthouse) && ($lighthouse['available'] ?? false),
                'axe' => is_array($axe) && ($axe['available'] ?? false),
                'broken_links' => $isHome,
                'js_errors' => true,
                'mobile' => $isHome && is_array($site['mobile'] ?? null),
            ],
            'captured_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $lighthouse
     * @return array<string, mixed>
     */
    private function vitals(?array $lighthouse): array
    {
        $audits = $lighthouse['audits'] ?? [];

        return [
            'lcp_ms' => $this->numericAudit($audits, 'largest-contentful-paint'),
            'cls' => $this->numericAudit($audits, 'cumulative-layout-shift'),
            'tbt_ms' => $this->numericAudit($audits, 'total-blocking-time'),
            'fcp_ms' => $this->numericAudit($audits, 'first-contentful-paint'),
            'speed_index_ms' => $this->numericAudit($audits, 'speed-index'),
            'tti_ms' => $this->numericAudit($audits, 'interactive'),
            'ttfb_ms' => $this->numericAudit($audits, 'server-response-time'),
            'total_byte_weight' => $this->numericAudit($audits, 'total-byte-weight'),
            'performance_score' => $lighthouse['categories']['performance'] ?? null,
            'accessibility_score' => $lighthouse['categories']['accessibility'] ?? null,
            'seo_score' => $lighthouse['categories']['seo'] ?? null,
            'best_practices_score' => $lighthouse['categories']['best-practices'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $audits
     */
    private function numericAudit(array $audits, string $key): ?float
    {
        $value = $audits[$key]['numeric_value'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $stats
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, array<string, mixed>>  $failures
     * @return array<string, mixed>
     */
    private function summary(array $site, array $stats, array $pages, array $failures): array
    {
        $lighthouse = $site['lighthouse'] ?? null;
        $axePages = 0;

        foreach ($pages as $page) {
            if (is_array($page['axe'] ?? null) && ($page['axe']['available'] ?? false)) {
                $axePages++;
            }
        }

        return [
            'site_name' => $site['site_name'] ?? null,
            'domain' => $site['domain'] ?? null,
            'entry_url' => $site['entry_url'] ?? null,
            'final_url' => $site['final_url'] ?? null,
            'title' => $site['title'] ?? null,
            'meta_description' => $site['meta_description'] ?? null,
            'lang' => $site['lang'] ?? null,
            'favicon' => $site['favicon'] ?? null,
            'has_ssl' => (bool) ($site['has_ssl'] ?? false),
            'security_headers' => $site['security_headers'] ?? [],
            'robots' => $site['robots'] ?? [],
            'mobile' => $site['mobile'] ?? null,
            'lighthouse_available' => is_array($lighthouse) && ($lighthouse['available'] ?? false),
            'lighthouse_categories' => is_array($lighthouse) ? ($lighthouse['categories'] ?? null) : null,
            'axe_available' => $axePages > 0,
            'axe_pages' => $axePages,
            'blocked_requests' => count($site['blocked_requests'] ?? []),
            'broken_links' => count($site['broken_links'] ?? []),
            'inspected_pages' => (int) ($stats['pages_crawled'] ?? count($pages)),
            'failed_pages' => (int) ($stats['pages_failed'] ?? count($failures)),
            'robots_blocked' => (int) ($stats['robots_blocked'] ?? 0),
            'failures' => array_slice($failures, 0, 20),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}

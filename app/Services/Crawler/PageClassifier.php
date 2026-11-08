<?php

namespace App\Services\Crawler;

use App\Enums\PageType;

class PageClassifier
{
    /**
     * Classify a crawled page from URL and extraction signals.
     *
     * @param  array<string, mixed>  $page
     */
    public function classify(string $url, array $page = []): PageType
    {
        $path = $this->path($url);

        if ($path === '/' || $path === '') {
            return PageType::Home;
        }

        if ((int) ($page['depth'] ?? 1) === 0) {
            return PageType::Home;
        }

        $haystack = strtolower(trim(implode(' ', array_filter([
            $path,
            (string) ($page['title'] ?? ''),
            (string) ($page['h1_text'] ?? ($page['h1'] ?? '')),
        ]))));

        $rules = [
            PageType::Legal->value => '/\b(privacy|terms|legal|cookie|gdpr|refund|disclaimer)\b/',
            PageType::Contact->value => '/\b(contact|get-in-touch|enquir|inquir|request-a-quote|book-a-call|talk|speak|chat)\b/',
            PageType::Pricing->value => '/\b(pricing|plans|packages|cost|rates|subscription)\b/',
            PageType::Blog->value => '/\b(blogs?|news|insights?|articles?|resources?|guides?|posts?|journal)\b/',
            PageType::About->value => '/\b(about|team|company|who-we-are|story|mission|leadership|careers)\b/',
            PageType::Landing->value => '/\b(landing|lp|offer|campaign|promo|free-trial|get-started|webinar|event)\b/',
            PageType::Product->value => '/\b(product|shop|store|cart|checkout|collection|item|category)\b/',
            PageType::Service->value => '/\b(service|solution|what-we-do|capabilit|consulting|treatment|procedure|clinic)\b/',
        ];

        foreach ($rules as $type => $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return PageType::from($type);
            }
        }

        return PageType::Other;
    }

    private function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        $path = '/'.trim(strtolower($path), '/');

        return $path === '/' ? '/' : $path;
    }
}

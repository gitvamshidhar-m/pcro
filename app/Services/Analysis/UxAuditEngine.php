<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\CheckStatus;

class UxAuditEngine extends AbstractAuditEngine
{
    public function category(): AuditCategory
    {
        return AuditCategory::Ux;
    }

    public function audit(CrawlAnalysisContext $context): array
    {
        return [
            $this->navigation($context),
            $this->mobileViewport($context),
            $this->readability($context),
            $this->contentHierarchy($context),
            $this->pageStructure($context),
            $this->layoutConsistency($context),
        ];
    }

    private function navigation(CrawlAnalysisContext $context): CheckResult
    {
        $home = $context->homePage;
        $extraction = $home ? $context->extraction($home) : [];
        $navCount = (int) ($extraction['nav_count'] ?? count($extraction['nav_links'] ?? []));
        $hasNav = (bool) ($extraction['has_nav'] ?? false);
        $navLabels = array_values(array_filter(array_map(
            fn ($link): ?string => is_array($link) ? ($link['text'] ?? null) : null,
            $extraction['nav_links'] ?? [],
        )));

        if ($context->inspectedCount() === 0) {
            return $this->result($context, 'ux.navigation_clarity', CheckStatus::NotApplicable, [
                'observed' => 'No pages were successfully inspected, so navigation could not be assessed.',
                'has_nav' => $hasNav,
                'nav_count' => $navCount,
            ]);
        }

        if ($navCount === 0 && ! $hasNav) {
            return $this->result($context, 'ux.navigation_clarity', CheckStatus::Fail, [
                'observed' => 'No primary navigation was detected on the homepage.',
                'has_nav' => $hasNav,
                'nav_count' => $navCount,
            ]);
        }

        $min = (int) config('cro.thresholds.min_nav_links', 3);
        $max = (int) config('cro.thresholds.max_nav_links', 12);

        $status = CheckStatus::Pass;

        if ($navCount < $min || $navCount > $max) {
            $status = CheckStatus::Warning;
        }

        return $this->result($context, 'ux.navigation_clarity', $status, [
            'observed' => $navCount < $min
                ? "The homepage exposes only {$navCount} navigation links; visitors may struggle to find key pages."
                : ($navCount > $max
                    ? "The homepage exposes {$navCount} navigation links, which is more than the recommended {$max}."
                    : "The homepage exposes {$navCount} navigation links, within the recommended range of {$min}-{$max}."),
            'has_nav' => $hasNav,
            'nav_count' => $navCount,
            'minimum' => $min,
            'maximum' => $max,
            'labels' => array_slice($navLabels, 0, 12),
        ]);
    }

    private function mobileViewport(CrawlAnalysisContext $context): CheckResult
    {
        if (! $context->mobileAvailable()) {
            return $this->result($context, 'ux.mobile_viewport', CheckStatus::NotApplicable, [
                'observed' => 'A mobile rendering check could not be captured, so mobile usability was not assessed.',
                'availability' => false,
            ]);
        }

        $mobile = $context->mobile();
        $viewportMeta = $mobile['viewport_meta'] ?? null;
        $overflow = (bool) ($mobile['horizontal_overflow'] ?? false);
        $smallTargets = (int) ($mobile['small_tap_targets'] ?? 0);
        $bodyFontSize = $mobile['body_font_size'] ?? null;

        if ($viewportMeta === null || $overflow) {
            return $this->result($context, 'ux.mobile_viewport', CheckStatus::Fail, [
                'observed' => $viewportMeta === null
                    ? 'No responsive viewport meta tag was detected.'
                    : 'Horizontal overflow was detected on the mobile viewport.',
                'viewport_meta' => $viewportMeta,
                'horizontal_overflow' => $overflow,
                'small_tap_targets' => $smallTargets,
                'body_font_size' => $bodyFontSize,
            ]);
        }

        $status = $smallTargets > 0 ? CheckStatus::Warning : CheckStatus::Pass;

        return $this->result($context, 'ux.mobile_viewport', $status, [
            'observed' => $smallTargets > 0
                ? "{$smallTargets} interactive elements are smaller than 40px on the mobile viewport."
                : 'The mobile viewport is responsive and no horizontal overflow was detected.',
            'viewport_meta' => $viewportMeta,
            'horizontal_overflow' => $overflow,
            'small_tap_targets' => $smallTargets,
            'body_font_size' => $bodyFontSize,
        ]);
    }

    private function readability(CrawlAnalysisContext $context): CheckResult
    {
        $home = $context->homePage;
        $extraction = $home ? $context->extraction($home) : [];
        $wordCount = (int) ($extraction['word_count'] ?? 0);
        $readingEase = $extraction['reading_ease'] ?? null;
        $avgSentenceWords = $extraction['avg_sentence_words'] ?? null;
        $longWordRatio = $extraction['long_word_ratio'] ?? null;

        if ($wordCount < 50) {
            return $this->result($context, 'ux.readability', CheckStatus::NotApplicable, [
                'observed' => 'The homepage has too little copy to assess readability.',
                'word_count' => $wordCount,
                'reading_ease' => $readingEase,
            ]);
        }

        if ($readingEase === null) {
            return $this->result($context, 'ux.readability', CheckStatus::NotApplicable, [
                'observed' => 'A readability score could not be calculated from the extracted copy.',
                'word_count' => $wordCount,
                'avg_sentence_words' => $avgSentenceWords,
            ]);
        }

        $status = match (true) {
            $readingEase >= 50 && ($avgSentenceWords === null || $avgSentenceWords <= 25) => CheckStatus::Pass,
            $readingEase >= 30 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'ux.readability', $status, [
            'observed' => $status === CheckStatus::Pass
                ? "Homepage copy has a Flesch reading-ease score of {$readingEase}."
                : "Homepage copy has a Flesch reading-ease score of {$readingEase}, which is below the recommended 50.",
            'word_count' => $wordCount,
            'reading_ease' => $readingEase,
            'avg_sentence_words' => $avgSentenceWords,
            'long_word_ratio' => $longWordRatio,
        ]);
    }

    private function contentHierarchy(CrawlAnalysisContext $context): CheckResult
    {
        if ($context->inspectedCount() === 0) {
            return $this->result($context, 'ux.content_hierarchy', CheckStatus::NotApplicable, [
                'observed' => 'No pages were successfully inspected.',
            ]);
        }

        $pagesWithoutH1 = [];
        $pagesWithMultipleH1 = [];
        $jumpCount = 0;

        foreach ($context->pages as $page) {
            $extraction = $context->extraction($page);
            $h1Count = (int) ($extraction['h1_count'] ?? 0);

            if ($h1Count === 0) {
                $pagesWithoutH1[] = $page->url;
            }

            if ($h1Count > 1) {
                $pagesWithMultipleH1[] = $page->url;
            }

            $levels = array_values(array_filter(array_map(
                fn ($heading): ?int => isset($heading['level']) ? (int) $heading['level'] : null,
                $extraction['headings'] ?? [],
            )));

            for ($i = 1, $count = count($levels); $i < $count; $i++) {
                if ($levels[$i] - $levels[$i - 1] > 1) {
                    $jumpCount++;
                }
            }
        }

        $home = $context->homePage;
        $homeH1Count = $home ? (int) ($context->extraction($home)['h1_count'] ?? 0) : 0;

        if ($homeH1Count === 0) {
            return $this->result($context, 'ux.content_hierarchy', CheckStatus::Fail, [
                'observed' => 'The homepage does not have a single descriptive H1.',
                'pages_without_h1' => $this->limitUrls($pagesWithoutH1),
                'pages_with_multiple_h1' => $this->limitUrls($pagesWithMultipleH1),
                'heading_jumps' => $jumpCount,
            ]);
        }

        $status = ($pagesWithoutH1 === [] && $pagesWithMultipleH1 === [] && $jumpCount === 0)
            ? CheckStatus::Pass
            : CheckStatus::Warning;

        return $this->result($context, 'ux.content_hierarchy', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'Each inspected page has a single H1 and a coherent heading order.'
                : 'Some inspected pages have missing, multiple or out-of-order headings.',
            'pages_without_h1' => $this->limitUrls($pagesWithoutH1),
            'pages_with_multiple_h1' => $this->limitUrls($pagesWithMultipleH1),
            'heading_jumps' => $jumpCount,
        ]);
    }

    private function pageStructure(CrawlAnalysisContext $context): CheckResult
    {
        $home = $context->homePage;
        $extraction = $home ? $context->extraction($home) : [];
        $landmarks = [
            'header' => (bool) ($extraction['has_header'] ?? false),
            'main' => (bool) ($extraction['has_main'] ?? false),
            'nav' => (bool) ($extraction['has_nav'] ?? false),
            'footer' => (bool) ($extraction['has_footer'] ?? false),
        ];
        $present = count(array_filter($landmarks));

        if ($context->inspectedCount() === 0) {
            return $this->result($context, 'ux.page_structure', CheckStatus::NotApplicable, [
                'observed' => 'No pages were successfully inspected.',
            ]);
        }

        $status = match (true) {
            $present === 4 => CheckStatus::Pass,
            $present >= 3 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'ux.page_structure', $status, [
            'observed' => "The homepage exposes {$present} of 4 core landmarks (header, main, nav, footer).",
            'landmarks' => $landmarks,
            'present' => $present,
        ]);
    }

    private function layoutConsistency(CrawlAnalysisContext $context): CheckResult
    {
        $lighthouse = $context->lighthouse();
        $cls = data_get($lighthouse, 'audits.cumulative-layout-shift.numeric_value');
        $mobile = $context->mobile();
        $overflow = (bool) ($mobile['horizontal_overflow'] ?? false);

        if ($cls === null && ! $context->mobileAvailable()) {
            return $this->result($context, 'ux.layout_consistency', CheckStatus::NotApplicable, [
                'observed' => 'Neither layout-shift nor mobile rendering evidence is available.',
                'cls' => $cls,
                'horizontal_overflow' => $overflow,
            ]);
        }

        $status = CheckStatus::Pass;

        if ($overflow) {
            $status = CheckStatus::Fail;
        } elseif (is_numeric($cls)) {
            $cls = (float) $cls;

            if ($cls > (float) config('cro.thresholds.cls_warn', 0.25)) {
                $status = CheckStatus::Fail;
            } elseif ($cls > (float) config('cro.thresholds.cls_good', 0.1)) {
                $status = CheckStatus::Warning;
            }
        }

        return $this->result($context, 'ux.layout_consistency', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'No significant layout-shift or horizontal-overflow issue was detected.'
                : 'Layout stability or horizontal-overflow issues were detected.',
            'cls' => $cls,
            'cls_good_threshold' => (float) config('cro.thresholds.cls_good', 0.1),
            'cls_warn_threshold' => (float) config('cro.thresholds.cls_warn', 0.25),
            'horizontal_overflow' => $overflow,
        ]);
    }
}

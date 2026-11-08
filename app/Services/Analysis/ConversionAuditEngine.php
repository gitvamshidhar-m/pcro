<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\CheckStatus;
use Illuminate\Support\Collection;

class ConversionAuditEngine extends AbstractAuditEngine
{
    public function category(): AuditCategory
    {
        return AuditCategory::Conversion;
    }

    public function audit(CrawlAnalysisContext $context): array
    {
        return [
            $this->primaryCtaPresent($context),
            $this->ctaWording($context),
            $this->ctaVisibility($context),
            $this->formPresent($context),
            $this->formComplexity($context),
            $this->formPositioning($context),
            $this->leadCapture($context),
            $this->journeyClarity($context),
        ];
    }

    private function conversionPages(CrawlAnalysisContext $context): Collection
    {
        $pages = $context->conversionPages();

        return $pages->isEmpty() ? $context->pages : $pages;
    }

    private function primaryCtaPresent(CrawlAnalysisContext $context): CheckResult
    {
        $pages = $this->conversionPages($context);

        if ($pages->isEmpty()) {
            return $this->result($context, 'conversion.primary_cta_present', CheckStatus::NotApplicable, [
                'observed' => 'No conversion-critical pages were successfully inspected.',
            ]);
        }

        $missing = [];
        $counts = [];

        foreach ($pages as $page) {
            $extraction = $context->extraction($page);
            $ctaCount = count($extraction['ctas'] ?? []);
            $primaryCount = (int) ($extraction['primary_ctas'] ?? 0);
            $counts[$page->url] = ['ctas' => $ctaCount, 'primary' => $primaryCount];

            if ($ctaCount === 0 && $primaryCount === 0) {
                $missing[] = $page->url;
            }
        }

        if ($missing !== []) {
            $status = count($missing) === $pages->count() ? CheckStatus::Fail : CheckStatus::Warning;
        } else {
            $status = CheckStatus::Pass;
        }

        return $this->result($context, 'conversion.primary_cta_present', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'Every inspected conversion-critical page exposes at least one call to action.'
                : count($missing).' of '.$pages->count().' conversion-critical pages expose no call to action.',
            'pages' => $this->limitUrls($pages->pluck('url')->all()),
            'missing' => $this->limitUrls($missing),
            'counts' => array_slice($counts, 0, 8, true),
        ]);
    }

    private function ctaWording(CrawlAnalysisContext $context): CheckResult
    {
        $vaguePatterns = ['submit', 'click here', 'learn more', 'read more', 'more info', 'go', 'continue', 'send', 'enter'];

        $texts = [];

        foreach ($this->conversionPages($context) as $page) {
            foreach (($context->extraction($page)['ctas'] ?? []) as $cta) {
                if (is_array($cta) && isset($cta['text'])) {
                    $texts[] = (string) $cta['text'];
                }
            }
        }

        if ($texts === []) {
            return $this->result($context, 'conversion.cta_wording', CheckStatus::NotApplicable, [
                'observed' => 'No CTA labels were extracted to assess wording.',
            ]);
        }

        $vague = array_filter($texts, fn (string $text): bool => $this->textContains($text, $vaguePatterns));
        $vagueRatio = count($vague) / max(1, count($texts));

        $status = match (true) {
            $vagueRatio <= 0.2 => CheckStatus::Pass,
            $vagueRatio <= 0.5 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'conversion.cta_wording', $status, [
            'observed' => count($vague).' of '.count($texts).' CTA labels use vague or generic wording.',
            'total_ctas' => count($texts),
            'vague_ctas' => count($vague),
            'vague_ratio' => round($vagueRatio, 2),
            'examples' => array_slice(array_values($vague), 0, 8),
        ]);
    }

    private function ctaVisibility(CrawlAnalysisContext $context): CheckResult
    {
        $home = $context->homePage;
        $extraction = $home ? $context->extraction($home) : [];
        $ctas = $extraction['ctas'] ?? [];
        $aboveFold = (int) ($extraction['above_fold_ctas'] ?? 0);

        if ($ctas === []) {
            return $this->result($context, 'conversion.cta_visibility', CheckStatus::NotApplicable, [
                'observed' => 'No CTAs were detected, so visibility could not be assessed.',
            ]);
        }

        $status = $aboveFold >= 1 ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'conversion.cta_visibility', $status, [
            'observed' => $aboveFold >= 1
                ? 'At least one CTA is visible in the opening viewport.'
                : 'No CTA is visible in the opening viewport.',
            'total_ctas' => count($ctas),
            'above_fold_ctas' => $aboveFold,
        ]);
    }

    private function formPresent(CrawlAnalysisContext $context): CheckResult
    {
        $pages = $this->conversionPages($context);
        $pagesWithForms = [];

        foreach ($pages as $page) {
            if (count($context->extraction($page)['forms'] ?? []) > 0) {
                $pagesWithForms[] = $page->url;
            }
        }

        if ($pages->isEmpty()) {
            return $this->result($context, 'conversion.form_present', CheckStatus::NotApplicable, [
                'observed' => 'No conversion-critical pages were successfully inspected.',
            ]);
        }

        if ($pagesWithForms === []) {
            return $this->result($context, 'conversion.form_present', CheckStatus::Fail, [
                'observed' => 'No enquiry or lead form was found on any conversion-critical page.',
                'inspected_pages' => $this->limitUrls($pages->pluck('url')->all()),
            ]);
        }

        $status = count($pagesWithForms) === 1 && $pages->count() > 1 ? CheckStatus::Warning : CheckStatus::Pass;

        return $this->result($context, 'conversion.form_present', $status, [
            'observed' => count($pagesWithForms).' of '.$pages->count().' conversion-critical pages contain a form.',
            'pages_with_forms' => $this->limitUrls($pagesWithForms),
        ]);
    }

    private function formComplexity(CrawlAnalysisContext $context): CheckResult
    {
        $forms = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['forms'] ?? []) as $form) {
                if (is_array($form)) {
                    $forms[] = ['page' => $page->url, 'fields' => (int) ($form['field_count'] ?? 0)];
                }
            }
        }

        if ($forms === []) {
            return $this->result($context, 'conversion.form_complexity', CheckStatus::NotApplicable, [
                'observed' => 'No forms were detected, so form complexity could not be assessed.',
            ]);
        }

        $maxFields = max(array_column($forms, 'fields'));
        $warn = (int) config('cro.thresholds.max_form_fields_warn', 4);
        $max = (int) config('cro.thresholds.max_form_fields', 6);

        $status = match (true) {
            $maxFields <= $warn => CheckStatus::Pass,
            $maxFields <= $max => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'conversion.form_complexity', $status, [
            'observed' => "The largest form asks for {$maxFields} fields.",
            'max_fields' => $maxFields,
            'recommended_maximum' => $max,
            'forms' => array_slice($forms, 0, 8),
        ]);
    }

    private function formPositioning(CrawlAnalysisContext $context): CheckResult
    {
        $forms = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['forms'] ?? []) as $form) {
                if (is_array($form)) {
                    $forms[] = [
                        'page' => $page->url,
                        'above_fold' => (bool) ($form['above_fold'] ?? false),
                        'page_type' => $page->page_type->value,
                    ];
                }
            }
        }

        if ($forms === []) {
            return $this->result($context, 'conversion.form_positioning', CheckStatus::NotApplicable, [
                'observed' => 'No forms were detected, so form positioning could not be assessed.',
            ]);
        }

        $aboveFold = array_filter($forms, fn (array $form): bool => $form['above_fold']);
        $onContact = array_filter($forms, fn (array $form): bool => $form['page_type'] === 'contact');

        $status = ($aboveFold !== [] || $onContact !== [])
            ? CheckStatus::Pass
            : CheckStatus::Warning;

        return $this->result($context, 'conversion.form_positioning', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'At least one form is either above the fold or on the contact page.'
                : 'Forms exist but none are above the fold or on the contact page.',
            'total_forms' => count($forms),
            'above_fold_forms' => count($aboveFold),
            'forms_on_contact_page' => count($onContact),
        ]);
    }

    private function leadCapture(CrawlAnalysisContext $context): CheckResult
    {
        $forms = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['forms'] ?? []) as $form) {
                if (is_array($form)) {
                    $forms[] = $form;
                }
            }
        }

        $captureCtas = 0;

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['ctas'] ?? []) as $cta) {
                if (is_array($cta) && $this->textContains((string) ($cta['text'] ?? ''), ['newsletter', 'subscribe', 'download', 'checklist', 'guide', 'callback', 'get updates'])) {
                    $captureCtas++;
                }
            }
        }

        $secondaryForms = array_filter($forms, fn (array $form): bool => (int) ($form['field_count'] ?? 0) <= 2);

        if ($forms === [] && $captureCtas === 0) {
            return $this->result($context, 'conversion.lead_capture', CheckStatus::Fail, [
                'observed' => 'No secondary or low-commitment lead-capture path was detected.',
                'forms' => count($forms),
                'capture_ctas' => $captureCtas,
            ]);
        }

        $status = ($secondaryForms !== [] || $captureCtas > 0)
            ? CheckStatus::Pass
            : CheckStatus::Warning;

        return $this->result($context, 'conversion.lead_capture', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'A low-commitment lead-capture path exists.'
                : 'Only the main conversion form is available; no secondary capture path was detected.',
            'forms' => count($forms),
            'low_field_forms' => count($secondaryForms),
            'capture_ctas' => $captureCtas,
        ]);
    }

    private function journeyClarity(CrawlAnalysisContext $context): CheckResult
    {
        $types = $context->pages->pluck('page_type')->map(fn ($type) => $type->value)->unique()->values()->all();
        $important = array_intersect($types, ['pricing', 'contact', 'product', 'service', 'landing']);
        $count = count($important);

        if ($context->inspectedCount() === 0) {
            return $this->result($context, 'conversion.journey_clarity', CheckStatus::NotApplicable, [
                'observed' => 'No pages were successfully inspected.',
            ]);
        }

        $status = match (true) {
            $count >= 2 => CheckStatus::Pass,
            $count === 1 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'conversion.journey_clarity', $status, [
            'observed' => $count >= 2
                ? 'Key conversion pages are discoverable from the crawled navigation.'
                : 'Few key conversion pages were discovered during the crawl.',
            'discovered_types' => $types,
            'important_types_found' => array_values($important),
            'count' => $count,
        ]);
    }
}

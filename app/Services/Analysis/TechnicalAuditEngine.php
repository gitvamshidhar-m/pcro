<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\CheckStatus;

class TechnicalAuditEngine extends AbstractAuditEngine
{
    public function category(): AuditCategory
    {
        return AuditCategory::Technical;
    }

    public function audit(CrawlAnalysisContext $context): array
    {
        return [
            $this->performance($context),
            $this->coreWebVitals($context),
            $this->accessibility($context),
            $this->imageOptimisation($context),
            $this->brokenLinks($context),
            $this->jsErrors($context),
            $this->mobilePerformance($context),
        ];
    }

    private function performance(CrawlAnalysisContext $context): CheckResult
    {
        if (! $context->lighthouseAvailable()) {
            return $this->result($context, 'technical.performance', CheckStatus::NotApplicable, [
                'observed' => 'Lighthouse did not run successfully, so performance was not assessed.',
                'lighthouse_available' => false,
            ]);
        }

        $score = data_get($context->lighthouse(), 'categories.performance');

        if (! is_numeric($score)) {
            return $this->result($context, 'technical.performance', CheckStatus::NotApplicable, [
                'observed' => 'Lighthouse did not report a performance score.',
                'lighthouse_available' => true,
            ]);
        }

        $score = (int) $score;
        $good = (int) config('cro.thresholds.lighthouse_good', 90);
        $warn = (int) config('cro.thresholds.lighthouse_warn', 50);

        $status = match (true) {
            $score >= $good => CheckStatus::Pass,
            $score >= $warn => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'technical.performance', $status, [
            'observed' => "Lighthouse reports a mobile performance score of {$score}.",
            'score' => $score,
            'good_threshold' => $good,
            'warn_threshold' => $warn,
        ]);
    }

    private function coreWebVitals(CrawlAnalysisContext $context): CheckResult
    {
        if (! $context->lighthouseAvailable()) {
            return $this->result($context, 'technical.core_web_vitals', CheckStatus::NotApplicable, [
                'observed' => 'Lighthouse did not run successfully, so Core Web Vitals were not assessed.',
                'lighthouse_available' => false,
            ]);
        }

        $lighthouse = $context->lighthouse();
        $lcp = data_get($lighthouse, 'audits.largest-contentful-paint.numeric_value');
        $cls = data_get($lighthouse, 'audits.cumulative-layout-shift.numeric_value');
        $tbt = data_get($lighthouse, 'audits.total-blocking-time.numeric_value');

        if (! is_numeric($lcp) && ! is_numeric($cls) && ! is_numeric($tbt)) {
            return $this->result($context, 'technical.core_web_vitals', CheckStatus::NotApplicable, [
                'observed' => 'Lighthouse did not report LCP, CLS or TBT values.',
            ]);
        }

        $failures = [];
        $warnings = [];

        if (is_numeric($lcp)) {
            $lcp = (float) $lcp;

            if ($lcp > (float) config('cro.thresholds.lcp_warn_ms', 4000)) {
                $failures[] = 'LCP';
            } elseif ($lcp > (float) config('cro.thresholds.lcp_good_ms', 2500)) {
                $warnings[] = 'LCP';
            }
        }

        if (is_numeric($cls)) {
            $cls = (float) $cls;

            if ($cls > (float) config('cro.thresholds.cls_warn', 0.25)) {
                $failures[] = 'CLS';
            } elseif ($cls > (float) config('cro.thresholds.cls_good', 0.1)) {
                $warnings[] = 'CLS';
            }
        }

        if (is_numeric($tbt)) {
            $tbt = (float) $tbt;

            if ($tbt > (float) config('cro.thresholds.tbt_warn_ms', 600)) {
                $failures[] = 'TBT';
            } elseif ($tbt > (float) config('cro.thresholds.tbt_good_ms', 200)) {
                $warnings[] = 'TBT';
            }
        }

        $status = $failures !== [] ? CheckStatus::Fail : ($warnings !== [] ? CheckStatus::Warning : CheckStatus::Pass);

        return $this->result($context, 'technical.core_web_vitals', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'Core Web Vitals are within the recommended thresholds.'
                : 'One or more Core Web Vitals are outside the recommended thresholds.',
            'lcp_ms' => is_numeric($lcp) ? $lcp : null,
            'cls' => is_numeric($cls) ? $cls : null,
            'tbt_ms' => is_numeric($tbt) ? $tbt : null,
            'failing' => $failures,
            'warning' => $warnings,
        ]);
    }

    private function accessibility(CrawlAnalysisContext $context): CheckResult
    {
        $axePages = 0;
        $critical = 0;
        $serious = 0;
        $moderate = 0;
        $minor = 0;
        $totalNodes = 0;

        foreach ($context->pages as $page) {
            $axe = $page->metrics?->axe;

            if (! is_array($axe) || ! ($axe['available'] ?? false)) {
                continue;
            }

            $axePages++;
            $critical += (int) ($axe['critical'] ?? 0);
            $serious += (int) ($axe['serious'] ?? 0);
            $moderate += (int) ($axe['moderate'] ?? 0);
            $minor += (int) ($axe['minor'] ?? 0);
            $totalNodes += (int) ($axe['total_nodes'] ?? 0);
        }

        if ($axePages === 0) {
            return $this->result($context, 'technical.accessibility', CheckStatus::NotApplicable, [
                'observed' => 'No automated accessibility audit completed, so violations were not assessed.',
                'axe_pages' => 0,
            ]);
        }

        $status = match (true) {
            $critical > 0 || $serious >= 3 => CheckStatus::Fail,
            $serious > 0 || $moderate >= 5 => CheckStatus::Warning,
            default => CheckStatus::Pass,
        };

        return $this->result($context, 'technical.accessibility', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'No critical or serious automated accessibility violations were detected.'
                : 'Automated accessibility violations were detected.',
            'axe_pages' => $axePages,
            'critical' => $critical,
            'serious' => $serious,
            'moderate' => $moderate,
            'minor' => $minor,
            'total_nodes' => $totalNodes,
        ]);
    }

    private function imageOptimisation(CrawlAnalysisContext $context): CheckResult
    {
        $total = 0;
        $missingAlt = 0;
        $lazy = 0;

        foreach ($context->pages as $page) {
            $extraction = $context->extraction($page);
            $total += (int) ($extraction['image_count'] ?? 0);
            $missingAlt += (int) ($extraction['images_missing_alt'] ?? 0);
            $lazy += (int) ($extraction['lazy_images'] ?? 0);
        }

        if ($total === 0) {
            return $this->result($context, 'technical.image_optimisation', CheckStatus::NotApplicable, [
                'observed' => 'No content images were detected, so image optimisation was not assessed.',
                'images' => 0,
            ]);
        }

        $coverage = 1 - ($missingAlt / max(1, $total));
        $threshold = (float) config('cro.thresholds.alt_text_coverage', 0.9);

        $status = $coverage >= $threshold ? CheckStatus::Pass : ($coverage >= 0.7 ? CheckStatus::Warning : CheckStatus::Fail);

        return $this->result($context, 'technical.image_optimisation', $status, [
            'observed' => round($coverage * 100).'%'.' of content images have alternative text.',
            'images' => $total,
            'missing_alt' => $missingAlt,
            'lazy_images' => $lazy,
            'alt_coverage' => round($coverage, 3),
            'threshold' => $threshold,
        ]);
    }

    private function brokenLinks(CrawlAnalysisContext $context): CheckResult
    {
        if (! array_key_exists('broken_links', $context->summary())) {
            return $this->result($context, 'technical.broken_links', CheckStatus::NotApplicable, [
                'observed' => 'Broken-link checks were not reported for this crawl.',
            ]);
        }

        $broken = $context->brokenLinks();
        $count = count($broken);

        $status = match (true) {
            $count === 0 => CheckStatus::Pass,
            $count <= 2 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'technical.broken_links', $status, [
            'observed' => $count === 0
                ? 'No broken internal links were detected.'
                : "{$count} broken internal link(s) were detected.",
            'count' => $count,
            'examples' => array_slice(array_map(fn ($link): ?string => is_array($link) ? ($link['url'] ?? null) : null, $broken), 0, 8),
        ]);
    }

    private function jsErrors(CrawlAnalysisContext $context): CheckResult
    {
        $pagesWithErrors = 0;
        $samples = [];

        foreach ($context->pages as $page) {
            $errors = $context->extraction($page)['js_errors'] ?? [];

            if (is_array($errors) && $errors !== []) {
                $pagesWithErrors++;
                $samples = array_merge($samples, array_slice($errors, 0, 3));
            }
        }

        if ($context->inspectedCount() === 0) {
            return $this->result($context, 'technical.js_errors', CheckStatus::NotApplicable, [
                'observed' => 'No pages were successfully inspected.',
            ]);
        }

        $ratio = $pagesWithErrors / max(1, $context->inspectedCount());

        $status = match (true) {
            $pagesWithErrors === 0 => CheckStatus::Pass,
            $ratio <= 0.3 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'technical.js_errors', $status, [
            'observed' => $pagesWithErrors === 0
                ? 'No JavaScript runtime errors were captured.'
                : "JavaScript errors were captured on {$pagesWithErrors} of {$context->inspectedCount()} pages.",
            'pages_with_errors' => $pagesWithErrors,
            'inspected_pages' => $context->inspectedCount(),
            'samples' => array_slice($samples, 0, 6),
        ]);
    }

    private function mobilePerformance(CrawlAnalysisContext $context): CheckResult
    {
        if (! $context->mobileAvailable()) {
            return $this->result($context, 'technical.mobile_performance', CheckStatus::NotApplicable, [
                'observed' => 'Mobile rendering evidence is not available.',
                'availability' => false,
            ]);
        }

        $mobile = $context->mobile();
        $overflow = (bool) ($mobile['small_tap_targets'] ?? 0) > 0 || (bool) ($mobile['horizontal_overflow'] ?? false);
        $lighthouseScore = data_get($context->lighthouse(), 'categories.performance');

        $status = CheckStatus::Pass;

        if ((bool) ($mobile['horizontal_overflow'] ?? false)) {
            $status = CheckStatus::Fail;
        } elseif (is_numeric($lighthouseScore) && (int) $lighthouseScore < 50) {
            $status = CheckStatus::Fail;
        } elseif ((int) ($mobile['small_tap_targets'] ?? 0) > 0 || (is_numeric($lighthouseScore) && (int) $lighthouseScore < 90)) {
            $status = CheckStatus::Warning;
        }

        return $this->result($context, 'technical.mobile_performance', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'The mobile experience passes the responsive and performance checks.'
                : 'Mobile usability or performance risks were detected.',
            'horizontal_overflow' => (bool) ($mobile['horizontal_overflow'] ?? false),
            'small_tap_targets' => (int) ($mobile['small_tap_targets'] ?? 0),
            'lighthouse_performance' => is_numeric($lighthouseScore) ? (int) $lighthouseScore : null,
        ]);
    }
}

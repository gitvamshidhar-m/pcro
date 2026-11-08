<?php

namespace App\Services\Analysis;

final class FrictionAnalyzer
{
    /**
     * @return array{score: int, level: string, factors: array<int, array<string, mixed>>}
     */
    public function analyze(CrawlAnalysisContext $context): array
    {
        $factors = [];

        $add = function (string $key, bool $condition, array $evidence = []) use (&$factors): void {
            if (! $condition) {
                return;
            }

            $config = (array) (config("cro.friction.{$key}") ?? []);
            $factors[] = [
                'key' => $key,
                'label' => (string) ($config['label'] ?? $key),
                'penalty' => (int) ($config['penalty'] ?? 0),
                'evidence' => $evidence,
            ];
        };

        $maxFormFields = 0;

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['forms'] ?? []) as $form) {
                if (is_array($form)) {
                    $maxFormFields = max($maxFormFields, (int) ($form['field_count'] ?? 0));
                }
            }
        }

        $add('form_fields_over_6', $maxFormFields > (int) config('cro.thresholds.max_form_fields', 6), ['max_fields' => $maxFormFields]);
        $add('form_fields_over_4', $maxFormFields > (int) config('cro.thresholds.max_form_fields_warn', 4) && $maxFormFields <= (int) config('cro.thresholds.max_form_fields', 6), ['max_fields' => $maxFormFields]);

        $maxPrimaryCtas = 0;

        foreach ($context->pages as $page) {
            $maxPrimaryCtas = max($maxPrimaryCtas, (int) ($context->extraction($page)['primary_ctas'] ?? 0));
        }

        $add('multiple_primary_ctas', $maxPrimaryCtas > (int) config('cro.thresholds.max_primary_ctas', 3), ['max_primary_ctas' => $maxPrimaryCtas]);

        $hasCta = $context->pages->contains(fn ($page): bool => count($context->extraction($page)['ctas'] ?? []) > 0);
        $add('no_primary_cta', ! $hasCta, ['pages_with_ctas' => 0]);

        $emails = 0;
        $phones = 0;
        $addresses = 0;

        foreach ($context->pages as $page) {
            $contact = $context->extraction($page)['contact'] ?? [];
            $emails += count($contact['emails'] ?? []);
            $phones += count($contact['phones'] ?? []);
            $addresses += count($contact['addresses'] ?? []);
        }

        $add('missing_contact_info', $emails === 0 && $phones === 0 && $addresses === 0, ['emails' => $emails, 'phones' => $phones, 'addresses' => $addresses]);

        $testimonials = 0;
        $trustSignals = 0;

        foreach ($context->pages as $page) {
            $testimonials += count($context->extraction($page)['testimonials'] ?? []);
            $trustSignals += count($context->extraction($page)['trust_signals'] ?? []);
        }

        $add('missing_trust_proof', $testimonials === 0 && $trustSignals === 0, ['testimonials' => $testimonials, 'trust_signals' => $trustSignals]);

        $types = $context->pages->pluck('page_type')->map(fn ($type) => $type->value)->all();
        $hasPricing = in_array('pricing', $types, true);

        $offerCues = 0;

        foreach ($context->pages as $page) {
            $extraction = $context->extraction($page);
            $haystack = implode(' ', [
                (string) ($extraction['title'] ?? ''),
                (string) ($extraction['h1_text'] ?? ''),
                (string) ($extraction['intro_text'] ?? ''),
            ]);
            $offerCues += mb_substr_count(mb_strtolower($haystack), 'pricing')
                + mb_substr_count(mb_strtolower($haystack), 'demo')
                + mb_substr_count(mb_strtolower($haystack), 'trial');
        }

        $add('no_pricing_or_offer', ! $hasPricing && $offerCues === 0, ['has_pricing_page' => $hasPricing, 'offer_cues' => $offerCues]);

        $forms = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['forms'] ?? []) as $form) {
                if (is_array($form)) {
                    $forms[] = $form;
                }
            }
        }

        $secondaryForms = array_filter($forms, fn (array $form): bool => (int) ($form['field_count'] ?? 0) <= 2);
        $captureCtas = 0;

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['ctas'] ?? []) as $cta) {
                if (is_array($cta) && $this->textContains((string) ($cta['text'] ?? ''), ['newsletter', 'subscribe', 'download', 'checklist', 'guide', 'callback', 'get updates'])) {
                    $captureCtas++;
                }
            }
        }

        $add('no_lead_capture', $secondaryForms === [] && $captureCtas === 0 && $forms !== [], ['forms' => count($forms), 'capture_ctas' => $captureCtas]);

        $vaguePatterns = ['submit', 'click here', 'learn more', 'read more', 'more info', 'go', 'continue', 'send', 'enter'];
        $ctaTexts = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['ctas'] ?? []) as $cta) {
                if (is_array($cta) && isset($cta['text'])) {
                    $ctaTexts[] = (string) $cta['text'];
                }
            }
        }

        $vagueCount = count(array_filter($ctaTexts, fn (string $text): bool => $this->textContains($text, $vaguePatterns)));
        $vagueRatio = $ctaTexts === [] ? 0.0 : $vagueCount / count($ctaTexts);
        $add('vague_cta_wording', $ctaTexts !== [] && $vagueRatio > 0.5, ['vague' => $vagueCount, 'total' => count($ctaTexts)]);

        $dense = false;

        foreach ($context->pages as $page) {
            $extraction = $context->extraction($page);
            $wordCount = (int) ($extraction['word_count'] ?? 0);
            $avgSentence = $extraction['avg_sentence_words'] ?? null;

            if ($wordCount > (int) config('cro.thresholds.max_word_count_warn', 2500) || (is_numeric($avgSentence) && $avgSentence > 30)) {
                $dense = true;
            }
        }

        $add('long_dense_copy', $dense, []);

        $jsErrors = 0;

        foreach ($context->pages as $page) {
            $errors = $context->extraction($page)['js_errors'] ?? [];

            if (is_array($errors) && $errors !== []) {
                $jsErrors++;
            }
        }

        $add('js_errors', $jsErrors > 0, ['pages_with_errors' => $jsErrors]);

        $critical = 0;
        $serious = 0;

        foreach ($context->pages as $page) {
            $axe = $page->metrics?->axe;

            if (is_array($axe) && ($axe['available'] ?? false)) {
                $critical += (int) ($axe['critical'] ?? 0);
                $serious += (int) ($axe['serious'] ?? 0);
            }
        }

        $add('accessibility_violations', $critical > 0 || $serious > 0, ['critical' => $critical, 'serious' => $serious]);

        $mobile = $context->mobile();
        $overflow = (bool) ($mobile['horizontal_overflow'] ?? false);
        $smallTargets = (int) ($mobile['small_tap_targets'] ?? 0);
        $add('mobile_issues', $context->mobileAvailable() && ($overflow || $smallTargets > 0), ['horizontal_overflow' => $overflow, 'small_tap_targets' => $smallTargets]);

        $penaltyTotal = array_sum(array_column($factors, 'penalty'));
        $score = min((int) config('cro.friction_max', 100), $penaltyTotal);

        $level = match (true) {
            $score >= 75 => 'severe',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };

        return [
            'score' => $score,
            'level' => $level,
            'factors' => $factors,
        ];
    }

    private function textContains(string $haystack, array $needles): bool
    {
        $haystack = mb_strtolower($haystack);

        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}

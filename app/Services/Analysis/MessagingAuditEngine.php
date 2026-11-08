<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\CheckStatus;

class MessagingAuditEngine extends AbstractAuditEngine
{
    public function category(): AuditCategory
    {
        return AuditCategory::Messaging;
    }

    public function audit(CrawlAnalysisContext $context): array
    {
        return [
            $this->headlineClarity($context),
            $this->valueProposition($context),
            $this->benefitCommunication($context),
            $this->audienceRelevance($context),
            $this->differentiation($context),
            $this->offerClarity($context),
            $this->messageConsistency($context),
        ];
    }

    private function homeExtraction(CrawlAnalysisContext $context): array
    {
        $home = $context->homePage;

        return $home ? $context->extraction($home) : [];
    }

    private function headlineClarity(CrawlAnalysisContext $context): CheckResult
    {
        $extraction = $this->homeExtraction($context);
        $headline = $extraction['h1_text'] ?? null;
        $headline = is_string($headline) ? trim($headline) : null;
        $wordCount = $headline ? count(preg_split('/\s+/', $headline) ?: []) : 0;
        $generic = ['home', 'welcome', 'homepage', 'untitled', 'index'];

        if ($headline === null || $headline === '') {
            return $this->result($context, 'messaging.headline_clarity', CheckStatus::Fail, [
                'observed' => 'No homepage H1 headline was detected.',
                'headline' => null,
            ]);
        }

        $isGeneric = $this->textContains($headline, $generic) && $wordCount <= 2;
        $maxWords = (int) config('cro.thresholds.max_headline_words', 14);

        $status = match (true) {
            $isGeneric => CheckStatus::Fail,
            $wordCount > $maxWords + 6 => CheckStatus::Fail,
            $wordCount > $maxWords => CheckStatus::Warning,
            $wordCount <= 2 => CheckStatus::Warning,
            default => CheckStatus::Pass,
        };

        return $this->result($context, 'messaging.headline_clarity', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'The homepage headline is concise and specific.'
                : 'The homepage headline is missing, generic or too long.',
            'headline' => $this->cleanSnippet($headline, 160),
            'word_count' => $wordCount,
            'recommended_maximum' => $maxWords,
        ]);
    }

    private function valueProposition(CrawlAnalysisContext $context): CheckResult
    {
        $extraction = $this->homeExtraction($context);
        $intro = $extraction['intro_text'] ?? $extraction['main_text'] ?? null;
        $intro = is_string($intro) ? trim($intro) : '';
        $cues = ['save', 'increase', 'grow', 'reduce', 'faster', 'simple', 'secure', 'help', 'more', 'less', 'better', 'without', 'so you', 'outcome'];

        if ($intro === '') {
            return $this->result($context, 'messaging.value_proposition', CheckStatus::Fail, [
                'observed' => 'No supporting copy was detected beneath the headline.',
                'cue_count' => 0,
            ]);
        }

        $cueCount = $this->countMatches($intro, $cues);
        $length = mb_strlen($intro);

        $status = match (true) {
            $length >= 40 && $cueCount >= 1 => CheckStatus::Pass,
            $length >= 20 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'messaging.value_proposition', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'A value proposition with outcome language is present.'
                : 'The supporting copy does not clearly state an outcome or value.',
            'intro_snippet' => $this->cleanSnippet($intro, 220),
            'cue_count' => $cueCount,
            'length' => $length,
        ]);
    }

    private function benefitCommunication(CrawlAnalysisContext $context): CheckResult
    {
        $extraction = $this->homeExtraction($context);
        $mainText = $extraction['main_text'] ?? '';
        $wordCount = (int) ($extraction['word_count'] ?? 0);

        if ($wordCount < 50) {
            return $this->result($context, 'messaging.benefit_communication', CheckStatus::NotApplicable, [
                'observed' => 'Too little copy is available to assess benefit communication.',
                'word_count' => $wordCount,
            ]);
        }

        $cues = ['save', 'reduce', 'increase', 'grow', 'improve', 'faster', 'easier', 'simpler', 'time', 'cost', 'revenue', 'convert', 'win', 'protect', 'support'];
        $cueCount = $this->countMatches($mainText, $cues);

        $status = match (true) {
            $cueCount >= 5 => CheckStatus::Pass,
            $cueCount >= 2 => CheckStatus::Warning,
            default => CheckStatus::Fail,
        };

        return $this->result($context, 'messaging.benefit_communication', $status, [
            'observed' => $cueCount >= 5
                ? 'Copy connects features to outcomes and benefits.'
                : 'Copy lists few outcome or benefit cues.',
            'cue_count' => $cueCount,
            'word_count' => $wordCount,
        ]);
    }

    private function audienceRelevance(CrawlAnalysisContext $context): CheckResult
    {
        $extraction = $this->homeExtraction($context);
        $headline = (string) ($extraction['h1_text'] ?? '');
        $title = (string) ($extraction['title'] ?? '');
        $intro = (string) ($extraction['intro_text'] ?? '');
        $haystack = $headline.' '.$title.' '.$intro;

        $cues = ['for teams', 'for businesses', 'for founders', 'for marketers', 'for agencies', 'for clinics', 'for patients', 'for students', 'for enterprises', 'helping', 'built for', 'designed for', 'who ', 'busy'];

        $status = $this->textContains($haystack, $cues) ? CheckStatus::Pass : CheckStatus::Fail;

        return $this->result($context, 'messaging.audience_relevance', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'The page names the intended audience.'
                : 'The page does not clearly name who the offer is for.',
            'headline' => $this->cleanSnippet($headline, 120),
            'title' => $this->cleanSnippet($title, 120),
        ]);
    }

    private function differentiation(CrawlAnalysisContext $context): CheckResult
    {
        $extraction = $this->homeExtraction($context);
        $mainText = (string) ($extraction['main_text'] ?? '');
        $trustSignals = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['trust_signals'] ?? []) as $signal) {
                if (is_array($signal)) {
                    $trustSignals[] = (string) ($signal['label'] ?? $signal['key'] ?? '');
                }
            }
        }

        $cues = ['unlike', 'only', 'unique', 'first', 'patented', 'proprietary', 'specialist', 'expert', 'award', 'years', 'instead of', 'better than', 'why choose'];
        $count = $this->countMatches($mainText, $cues);
        $proofCount = count(array_filter($trustSignals, fn (string $label): bool => $this->textContains($label, ['award', 'experience', 'trusted', 'certified', 'customers'])));

        $status = ($count > 0 || $proofCount > 0) ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'messaging.differentiation', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'Differentiation or credibility cues are present.'
                : 'The page does not explain why the offer is different.',
            'differentiation_cues' => $count,
            'proof_signals' => $proofCount,
        ]);
    }

    private function offerClarity(CrawlAnalysisContext $context): CheckResult
    {
        $types = $context->pages->pluck('page_type')->map(fn ($type) => $type->value)->all();
        $hasPricing = in_array('pricing', $types, true);
        $hasContact = in_array('contact', $types, true);

        $offerCues = ['free trial', 'demo', 'quote', 'consultation', 'pricing', 'plans', 'book', 'call', 'download', 'guarantee', 'from £', 'from $', 'per month'];
        $cueCount = 0;

        foreach ($context->pages as $page) {
            $extraction = $context->extraction($page);
            $haystack = implode(' ', [
                (string) ($extraction['title'] ?? ''),
                (string) ($extraction['h1_text'] ?? ''),
                (string) ($extraction['intro_text'] ?? ''),
            ]);
            $cueCount += $this->countMatches($haystack, $offerCues);
        }

        $ctaCount = 0;

        foreach ($context->pages as $page) {
            $ctaCount += count($context->extraction($page)['ctas'] ?? []);
        }

        if ($cueCount === 0 && $ctaCount === 0) {
            return $this->result($context, 'messaging.offer_clarity', CheckStatus::Fail, [
                'observed' => 'Neither the offer nor the next step is explicit.',
                'offer_cues' => $cueCount,
                'ctas' => $ctaCount,
            ]);
        }

        $status = ($hasPricing || $hasContact || $cueCount >= 2) && $ctaCount > 0
            ? CheckStatus::Pass
            : CheckStatus::Warning;

        return $this->result($context, 'messaging.offer_clarity', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'The offer and next step are explicit.'
                : 'The offer or next step is only partially explicit.',
            'has_pricing_page' => $hasPricing,
            'has_contact_page' => $hasContact,
            'offer_cues' => $cueCount,
            'ctas' => $ctaCount,
        ]);
    }

    private function messageConsistency(CrawlAnalysisContext $context): CheckResult
    {
        $extraction = $this->homeExtraction($context);
        $title = (string) ($extraction['title'] ?? '');
        $headline = (string) ($extraction['h1_text'] ?? '');
        $meta = (string) ($extraction['meta_description'] ?? '');

        if ($headline === '') {
            return $this->result($context, 'messaging.message_consistency', CheckStatus::Fail, [
                'observed' => 'No headline is available to compare with the title and meta description.',
                'title' => $this->cleanSnippet($title, 120),
                'headline' => null,
                'meta_description' => $this->cleanSnippet($meta, 120),
            ]);
        }

        if ($meta === '') {
            return $this->result($context, 'messaging.message_consistency', CheckStatus::Warning, [
                'observed' => 'The meta description is missing, so message consistency could not be fully checked.',
                'title' => $this->cleanSnippet($title, 120),
                'headline' => $this->cleanSnippet($headline, 120),
                'meta_description' => null,
            ]);
        }

        $significant = fn (string $text): array => array_values(array_unique(array_filter(
            preg_split('/\s+/', mb_strtolower($text)) ?: [],
            fn (string $word): bool => mb_strlen($word) >= 4,
        )));

        $titleWords = $significant($title);
        $headlineWords = $significant($headline);
        $metaWords = $significant($meta);

        $overlap = count(array_intersect($titleWords, $headlineWords)) + count(array_intersect($headlineWords, $metaWords));

        $status = $overlap >= 2 ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'messaging.message_consistency', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'Title, headline and meta description are aligned.'
                : 'Title, headline and meta description tell different stories.',
            'overlap' => $overlap,
            'title' => $this->cleanSnippet($title, 120),
            'headline' => $this->cleanSnippet($headline, 120),
            'meta_description' => $this->cleanSnippet($meta, 120),
        ]);
    }
}

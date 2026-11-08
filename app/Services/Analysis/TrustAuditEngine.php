<?php

namespace App\Services\Analysis;

use App\Enums\AuditCategory;
use App\Enums\CheckStatus;
use App\Models\Page;

class TrustAuditEngine extends AbstractAuditEngine
{
    public function category(): AuditCategory
    {
        return AuditCategory::Trust;
    }

    public function audit(CrawlAnalysisContext $context): array
    {
        return [
            $this->testimonials($context),
            $this->reviews($context),
            $this->certifications($context),
            $this->security($context),
            $this->contactInformation($context),
            $this->socialProof($context),
            $this->businessCredibility($context),
        ];
    }

    private function testimonials(CrawlAnalysisContext $context): CheckResult
    {
        $testimonials = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['testimonials'] ?? []) as $testimonial) {
                $testimonials[] = ['page' => $page->url, 'author' => $testimonial['author'] ?? null];
            }
        }

        $withAuthor = count(array_filter($testimonials, fn (array $t): bool => is_string($t['author']) && trim($t['author']) !== ''));

        if ($testimonials === []) {
            return $this->result($context, 'trust.testimonials', CheckStatus::Fail, [
                'observed' => 'No customer testimonials were detected across the inspected pages.',
                'count' => 0,
            ]);
        }

        $status = ($withAuthor >= 1 || count($testimonials) >= 2) ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'trust.testimonials', $status, [
            'observed' => count($testimonials).' testimonial(s) were detected, '.$withAuthor.' with an attributable author.',
            'count' => count($testimonials),
            'with_author' => $withAuthor,
        ]);
    }

    private function reviews(CrawlAnalysisContext $context): CheckResult
    {
        $reviewSignals = 0;
        $pagesWithAggregateRating = 0;

        foreach ($context->pages as $page) {
            $extraction = $context->extraction($page);
            $reviewSignals += (int) ($extraction['review_count_on_page'] ?? 0);

            if (isset($extraction['aggregate_rating']) && $extraction['aggregate_rating'] !== null) {
                $pagesWithAggregateRating++;
            }

            foreach (($extraction['trust_signals'] ?? []) as $signal) {
                if (is_array($signal) && in_array($signal['key'] ?? '', ['rating', 'review'], true)) {
                    $reviewSignals++;
                }
            }
        }

        if ($reviewSignals === 0 && $pagesWithAggregateRating === 0) {
            return $this->result($context, 'trust.reviews', CheckStatus::Fail, [
                'observed' => 'No review or rating evidence was detected.',
                'review_signals' => 0,
                'pages_with_aggregate_rating' => 0,
            ]);
        }

        return $this->result($context, 'trust.reviews', CheckStatus::Pass, [
            'observed' => 'Review or rating evidence was detected.',
            'review_signals' => $reviewSignals,
            'pages_with_aggregate_rating' => $pagesWithAggregateRating,
        ]);
    }

    private function certifications(CrawlAnalysisContext $context): CheckResult
    {
        $certKeys = ['iso', 'soc2', 'gdpr', 'hipaa', 'pci', 'cyber_essentials', 'bcorp', 'certified', 'award'];
        $found = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['trust_signals'] ?? []) as $signal) {
                if (is_array($signal) && in_array($signal['key'] ?? '', $certKeys, true)) {
                    $found[] = $signal['label'] ?? $signal['key'];
                }
            }
        }

        if ($found === []) {
            return $this->result($context, 'trust.certifications', CheckStatus::Fail, [
                'observed' => 'No certification, accreditation or membership signal was detected.',
                'signals' => [],
            ]);
        }

        return $this->result($context, 'trust.certifications', CheckStatus::Pass, [
            'observed' => count($found).' certification or accreditation signal(s) were detected.',
            'signals' => array_values(array_unique($found)),
        ]);
    }

    private function security(CrawlAnalysisContext $context): CheckResult
    {
        $hasSsl = $context->hasSsl();
        $privacyLinks = $context->privacyLinks();
        $headers = $context->securityHeaders();
        $headerCount = count($headers);

        if (! $hasSsl) {
            return $this->result($context, 'trust.security', CheckStatus::Fail, [
                'observed' => 'The site is not served over HTTPS.',
                'has_ssl' => false,
                'privacy_links' => $privacyLinks,
                'security_headers' => $headerCount,
            ]);
        }

        $status = ($privacyLinks > 0 && $headerCount >= 1) ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'trust.security', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'HTTPS, privacy links and security headers are present.'
                : 'HTTPS is enabled, but privacy or security-header signals are incomplete.',
            'has_ssl' => true,
            'privacy_links' => $privacyLinks,
            'security_headers' => $headerCount,
        ]);
    }

    private function contactInformation(CrawlAnalysisContext $context): CheckResult
    {
        $emails = 0;
        $phones = 0;
        $addresses = 0;

        foreach ($context->pages as $page) {
            $contact = $context->extraction($page)['contact'] ?? [];
            $emails += count($contact['emails'] ?? []);
            $phones += count($contact['phones'] ?? []);
            $addresses += count($contact['addresses'] ?? []);
        }

        $channels = (int) ($emails > 0) + (int) ($phones > 0) + (int) ($addresses > 0);

        if ($channels === 0) {
            return $this->result($context, 'trust.contact_information', CheckStatus::Fail, [
                'observed' => 'No email, phone or address was detected on the inspected pages.',
                'emails' => $emails,
                'phones' => $phones,
                'addresses' => $addresses,
            ]);
        }

        $status = $channels >= 2 ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'trust.contact_information', $status, [
            'observed' => $channels >= 2
                ? 'Contact details are published across multiple channels.'
                : 'Only one contact channel was detected.',
            'emails' => $emails,
            'phones' => $phones,
            'addresses' => $addresses,
            'channels' => $channels,
        ]);
    }

    private function socialProof(CrawlAnalysisContext $context): CheckResult
    {
        $proofKeys = ['trusted', 'customers', 'case_study', 'experience', 'featured', 'award', 'guarantee'];
        $found = [];

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['trust_signals'] ?? []) as $signal) {
                if (is_array($signal) && in_array($signal['key'] ?? '', $proofKeys, true)) {
                    $found[] = $signal['label'] ?? $signal['key'];
                }
            }
        }

        $socialLinks = 0;

        foreach ($context->pages as $page) {
            $socialLinks += count($context->extraction($page)['social_links'] ?? []);
        }

        $signalCount = count(array_unique($found)) + ($socialLinks > 0 ? 1 : 0);

        if ($signalCount === 0) {
            return $this->result($context, 'trust.social_proof', CheckStatus::Fail, [
                'observed' => 'No broader social proof was detected.',
                'signals' => [],
                'social_links' => $socialLinks,
            ]);
        }

        $status = $signalCount >= 2 ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'trust.social_proof', $status, [
            'observed' => $signalCount >= 2
                ? 'Multiple social-proof signals were detected.'
                : 'Only limited social proof was detected.',
            'signals' => array_values(array_unique($found)),
            'social_links' => $socialLinks,
        ]);
    }

    private function businessCredibility(CrawlAnalysisContext $context): CheckResult
    {
        $aboutPages = $context->pagesOfType('about');
        $identitySignals = 0;

        if ($aboutPages->isNotEmpty()) {
            $identitySignals++;
        }

        if ($context->pages->contains(fn (Page $page): bool => ($context->extraction($page)['structured_name'] ?? null) !== null)) {
            $identitySignals++;
        }

        foreach ($context->pages as $page) {
            foreach (($context->extraction($page)['trust_signals'] ?? []) as $signal) {
                if (is_array($signal) && in_array($signal['key'] ?? '', ['experience', 'customers'], true)) {
                    $identitySignals++;
                }
            }
        }

        if ($identitySignals === 0 && $aboutPages->isEmpty()) {
            return $this->result($context, 'trust.business_credibility', CheckStatus::Fail, [
                'observed' => 'No clear business identity or credibility signals were detected.',
                'about_pages' => $aboutPages->count(),
            ]);
        }

        $status = ($aboutPages->isNotEmpty() && $identitySignals >= 2) ? CheckStatus::Pass : CheckStatus::Warning;

        return $this->result($context, 'trust.business_credibility', $status, [
            'observed' => $status === CheckStatus::Pass
                ? 'Business identity and credibility signals are present.'
                : 'Business identity is only partially explained.',
            'about_pages' => $aboutPages->count(),
            'identity_signals' => $identitySignals,
        ]);
    }
}

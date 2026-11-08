<?php

namespace App\Enums;

enum AuditCategory: string
{
    case Ux = 'ux';
    case Conversion = 'conversion';
    case Trust = 'trust';
    case Technical = 'technical';
    case Messaging = 'messaging';

    public function label(): string
    {
        return match ($this) {
            self::Ux => 'UX & Usability',
            self::Conversion => 'Conversion Elements',
            self::Trust => 'Trust & Credibility',
            self::Technical => 'Technical Performance',
            self::Messaging => 'Marketing Messaging',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Ux => 'UX',
            self::Conversion => 'Conversion',
            self::Trust => 'Trust',
            self::Technical => 'Technical',
            self::Messaging => 'Messaging',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Ux => 'Navigation, readability, hierarchy, mobile usability and page structure.',
            self::Conversion => 'CTA clarity, form complexity, lead capture and journey friction.',
            self::Trust => 'Proof, reviews, certifications, contact details and credibility signals.',
            self::Technical => 'Performance, Core Web Vitals, accessibility, images and broken links.',
            self::Messaging => 'Headline clarity, value proposition, benefits and audience relevance.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ux => '#6366f1',
            self::Conversion => '#8b5cf6',
            self::Trust => '#0ea5e9',
            self::Technical => '#10b981',
            self::Messaging => '#f59e0b',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return [self::Ux, self::Conversion, self::Trust, self::Technical, self::Messaging];
    }
}

<?php

namespace App\Enums;

enum RecommendationPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Critical => 'bg-red-50 text-red-700 ring-red-200',
            self::High => 'bg-orange-50 text-orange-700 ring-orange-200',
            self::Medium => 'bg-amber-50 text-amber-700 ring-amber-200',
            self::Low => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}

<?php

namespace App\Enums;

enum Severity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Low => 2,
            self::Medium => 3,
            self::High => 4,
            self::Critical => 5,
        };
    }

    public function downgrade(): self
    {
        return match ($this) {
            self::Critical => self::High,
            self::High => self::Medium,
            self::Medium => self::Low,
            self::Low, self::Info => self::Low,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => '#64748b',
            self::Low => '#0ea5e9',
            self::Medium => '#f59e0b',
            self::High => '#f97316',
            self::Critical => '#ef4444',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Info => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Low => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::Medium => 'bg-amber-50 text-amber-700 ring-amber-200',
            self::High => 'bg-orange-50 text-orange-700 ring-orange-200',
            self::Critical => 'bg-red-50 text-red-700 ring-red-200',
        };
    }
}

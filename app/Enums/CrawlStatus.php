<?php

namespace App\Enums;

enum CrawlStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Queued => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Running => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            self::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Failed => 'bg-red-50 text-red-700 ring-red-200',
            self::Cancelled => 'bg-slate-100 text-slate-500 ring-slate-200',
        };
    }
}

<?php

namespace App\Enums;

enum CheckStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
    case NotApplicable = 'not_applicable';

    public function score(): ?float
    {
        return match ($this) {
            self::Pass => 100.0,
            self::Warning => 55.0,
            self::Fail => 0.0,
            self::NotApplicable => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Warning => 'Needs attention',
            self::Fail => 'Fail',
            self::NotApplicable => 'Not assessed',
        };
    }
}

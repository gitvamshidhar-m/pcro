<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Analyst = 'analyst';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canManageWorkspace(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canAnalyze(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Analyst], true);
    }

    public function canDeleteData(): bool
    {
        return $this === self::Owner;
    }
}

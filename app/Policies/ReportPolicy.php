<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy extends CrawlArtifactPolicy
{
    public function view(User $user, Report $report): bool
    {
        return $report->crawl->workspace->hasMember($user);
    }
}

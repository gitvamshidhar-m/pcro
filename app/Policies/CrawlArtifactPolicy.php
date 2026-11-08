<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\User;

class CrawlArtifactPolicy
{
    public function view(User $user, Opportunity|Recommendation|Report $artifact): bool
    {
        return $artifact->crawl->workspace->hasMember($user);
    }
}

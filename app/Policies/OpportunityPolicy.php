<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy extends CrawlArtifactPolicy
{
    public function view(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->crawl->workspace->hasMember($user);
    }
}

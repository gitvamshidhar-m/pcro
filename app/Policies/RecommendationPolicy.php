<?php

namespace App\Policies;

use App\Models\Recommendation;
use App\Models\User;

class RecommendationPolicy extends CrawlArtifactPolicy
{
    public function view(User $user, Recommendation $recommendation): bool
    {
        return $recommendation->crawl->workspace->hasMember($user);
    }
}

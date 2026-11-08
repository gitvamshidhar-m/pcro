<?php

namespace App\Policies;

use App\Models\Crawl;
use App\Models\User;

class CrawlPolicy
{
    public function view(User $user, Crawl $crawl): bool
    {
        return $crawl->workspace->hasMember($user);
    }

    public function create(User $user, Crawl $crawl): bool
    {
        return $crawl->workspace->roleFor($user)?->canAnalyze() ?? false;
    }

    public function update(User $user, Crawl $crawl): bool
    {
        return $crawl->workspace->roleFor($user)?->canAnalyze() ?? false;
    }

    public function delete(User $user, Crawl $crawl): bool
    {
        return $crawl->workspace->roleFor($user)?->canDeleteData() ?? false;
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function view(User $user, Website $website): bool
    {
        return $website->workspace->hasMember($user);
    }

    public function delete(User $user, Website $website): bool
    {
        return $website->workspace->roleFor($user)?->canDeleteData() ?? false;
    }
}

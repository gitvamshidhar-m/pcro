<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $project->workspace->hasMember($user);
    }

    public function create(User $user, Project $project): bool
    {
        return $project->workspace->roleFor($user)?->canAnalyze() ?? false;
    }

    public function update(User $user, Project $project): bool
    {
        return $project->workspace->roleFor($user)?->canAnalyze() ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->workspace->roleFor($user)?->canDeleteData() ?? false;
    }
}

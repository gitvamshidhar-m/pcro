<?php

namespace App\Support;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class WorkspaceProvisioner
{
    public function createFor(User $user, ?string $name = null): Workspace
    {
        $name ??= $user->name."'s workspace";

        $workspace = Workspace::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'owner_id' => $user->id,
            'settings' => [
                'default_scan_pages' => config('cro.crawl.max_pages'),
            ],
        ]);

        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        if ($user->current_workspace_id === null) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        return $workspace;
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (Workspace::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

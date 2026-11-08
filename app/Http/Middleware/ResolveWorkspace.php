<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use App\Support\WorkspaceProvisioner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $workspace = null;

            if ($user->current_workspace_id !== null) {
                $workspace = $user->currentWorkspace;
            }

            if ($workspace === null || ! $workspace->hasMember($user)) {
                $workspace = $user->ownedWorkspaces()->orderBy('id')->first()
                    ?? $user->workspaces()->orderBy('workspaces.id')->first();
            }

            if ($workspace === null) {
                $workspace = app(WorkspaceProvisioner::class)->createFor($user);
            }

            if ($user->current_workspace_id !== $workspace->id) {
                $user->forceFill(['current_workspace_id' => $workspace->id])->save();
            }

            app(WorkspaceContext::class)->set($workspace);

            view()->share('currentWorkspace', $workspace);
            view()->share('availableWorkspaces', $user->allWorkspaces());
        }

        return $next($request);
    }
}

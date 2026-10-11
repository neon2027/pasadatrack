<?php

namespace App\Http\Middleware;

use App\Enums\TeamPermission;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $team = $request->route('current_team');
        abort_unless($team instanceof Team && $request->user()?->hasTeamPermission($team, TeamPermission::from($permission)), 403);

        return $next($request);
    }
}

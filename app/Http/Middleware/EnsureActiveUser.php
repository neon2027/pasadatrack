<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $pending = $request->session()->get('login.id');
        $checked = $user ? User::query()->find($user->id) : ((is_int($pending) || (is_string($pending) && ctype_digit($pending))) ? User::query()->whereKey($pending)->first() : null);
        $version = $request->session()->get($user ? 'auth.version' : 'login.version', 0);
        if (($user || $pending) && (! $checked || ! $checked->isActive() || $checked->auth_version !== (int) $version)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors(['google' => __('Your access has changed or been suspended. Sign in again or contact an administrator.')]);
        }
        if ($user && $checked) {
            Auth::setUser($checked);
        }

        return $next($request);
    }
}

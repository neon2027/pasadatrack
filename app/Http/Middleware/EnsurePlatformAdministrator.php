<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdministrator
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return to_route('security.edit')->with('status', 'administrator-two-factor-required');
        }
        if (! $request->session()->get('auth.two_factor_verified', false)) {
            $request->session()->put('url.intended', $request->url());
            Auth::logout();
            $request->session()->forget(['auth.version', 'auth.password_confirmed_at']);

            return to_route('login')->withErrors(['google' => __('Sign in again and complete two-factor authentication to access administration.')]);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectAdministratorTwoFactor
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->routeIs('two-factor.disable') && $request->user()?->isAdministrator(), 403, 'Administrators must retain two-factor authentication.');
        $response = $next($request);
        if ($request->routeIs('two-factor.confirm') && $response->getStatusCode() < 400 && $request->user()?->fresh()->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('auth.two_factor_verified', true);
        }

        return $response;
    }
}

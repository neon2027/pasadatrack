<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        $currentUser = $request->user()->fresh();
        if (! $currentUser || ! $currentUser->isActive() || $currentUser->auth_version !== (int) $request->session()->get('login.version', 0)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors(['google' => __('Your access has changed. Sign in again or contact an administrator.')]);
        }
        $request->session()->put('auth.version', $request->user()->auth_version);
        $request->session()->put('auth.two_factor_verified', true);
        $request->session()->forget('login.version');
        if ($request->session()->pull('google.two_factor_pending', false)) {
            $request->session()->put('auth.password_confirmed_at', now()->timestamp);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}

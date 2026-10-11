<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class CompleteGoogleLogin
{
    public function __construct(private LoginResponse $loginResponse) {}

    public function handle(Request $request, User $user): Response
    {
        $user = $user->fresh();
        abort_unless($user?->isActive(), 403);
        $request->session()->regenerate();
        if (! $request->user() && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->forget(['auth.password_confirmed_at', 'auth.two_factor_verified']);
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.version', $user->auth_version);
            $request->session()->put('login.remember', false);
            $request->session()->put('google.two_factor_pending', true);

            return to_route('two-factor.login');
        }
        Auth::login($user);
        $request->session()->put('auth.version', $user->auth_version);
        $request->session()->put('auth.password_confirmed_at', now()->timestamp);

        return $this->loginResponse->toResponse($request);
    }
}

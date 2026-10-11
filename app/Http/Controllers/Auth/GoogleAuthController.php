<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateGoogleUser;
use App\Actions\Auth\BeginGoogleAccountLink;
use App\Actions\Auth\CompleteGoogleLogin;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return to_route('login')->withErrors(['google' => __('Google sign-in is temporarily unavailable. Please try again later.')]);
        }

        $request->session()->forget(['login.id', 'login.remember', 'google.two_factor_pending']);
        $request->session()->put('google.login', [
            'user_id' => $request->user()?->id,
            'started_at' => now()->timestamp,
        ]);

        /** @var GoogleProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->setRequest($request)->enablePKCE()->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request, AuthenticateGoogleUser $authenticate, BeginGoogleAccountLink $link, CompleteGoogleLogin $login): HttpResponse|Response
    {
        $context = $request->session()->pull('google.login');
        $failureRoute = $request->user() ? 'password.confirm' : 'login';

        if (! $this->isConfigured() || ! is_array($context)
            || ! isset($context['started_at']) || $context['started_at'] < now()->subMinutes(10)->timestamp
            || ($context['user_id'] ?? null) !== $request->user()?->id || $request->has('error')) {
            $request->session()->forget(['state', 'code_verifier']);

            return to_route($failureRoute)->withErrors(['google' => __('Google sign-in was cancelled or expired. Please try again.')]);
        }

        try {
            /** @var GoogleProvider $provider */
            $provider = Socialite::driver('google');
            /** @var GoogleUser $googleUser */
            $googleUser = $provider->setRequest($request)->enablePKCE()->user();
            $user = $authenticate->handle($googleUser, $request->user());
        } catch (InvalidStateException) {
            return to_route($failureRoute)->withErrors(['google' => __('Your sign-in session expired. Please try again.')]);
        } catch (ValidationException $exception) {
            return to_route($failureRoute)->withErrors($exception->errors());
        } catch (GuzzleException|QueryException) {
            return to_route($failureRoute)->withErrors(['google' => __('We could not complete Google sign-in. Please try again.')]);
        } finally {
            $request->session()->forget(['state', 'code_verifier']);
        }

        if ($user->google_id === null) {
            try {
                return $link->handle($request, $user, $googleUser->getId());
            } catch (TransportExceptionInterface|QueryException $exception) {
                report($exception);

                return to_route($failureRoute)->withErrors(['google' => __('We could not send the verification email. Please try again or contact an administrator.')]);
            }
        }

        return $login->handle($request, $user);
    }

    public function confirm(Request $request): Response
    {
        $allowed = ['/admin/users', '/admin/roles', '/admin/levels', '/admin/audit', '/settings/profile', '/settings/security'];
        $returnTo = $request->query('return_to');
        if (is_string($returnTo) && in_array($returnTo, $allowed, true)) {
            $request->session()->put('url.intended', url($returnTo));
        } elseif ($request->session()->has('url.intended')) {
            $intended = $request->session()->get('url.intended');
            $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;
            if (is_string($path) && preg_match('#^/admin/(users|roles|levels)/[0-9]+$#', $path, $matches)) {
                $request->session()->put('url.intended', url('/admin/'.$matches[1]));
            }
        } else {
            $request->session()->put('url.intended', route('profile.edit'));
        }

        return Inertia::render('auth/confirm-password', ['googleConfigured' => $this->isConfigured()]);
    }

    private function isConfigured(): bool
    {
        return (bool) (config('services.google.client_id') && config('services.google.client_secret'));
    }
}

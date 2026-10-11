<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteGoogleLogin;
use App\Http\Controllers\Controller;
use App\Models\GoogleLinkChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GoogleLinkController extends Controller
{
    public function show(Request $request, GoogleLinkChallenge $challenge, string $token): Response
    {
        $this->validateChallenge($request, $challenge, $token);

        return Inertia::render('auth/link-email', ['mode' => 'confirm', 'challengeId' => $challenge->id, 'token' => $token]);
    }

    public function store(Request $request, GoogleLinkChallenge $challenge, string $token, CompleteGoogleLogin $login): HttpResponse
    {
        $user = DB::transaction(function () use ($request, $challenge, $token): User {
            $challenge = GoogleLinkChallenge::query()->lockForUpdate()->findOrFail($challenge->id);
            $this->validateChallenge($request, $challenge, $token);
            $user = User::query()->lockForUpdate()->findOrFail($challenge->user_id);
            abort_unless($user->isActive() && $user->google_id === null && $user->email === $challenge->email && $user->auth_version === $challenge->auth_version, 403);
            abort_if(User::query()->where('google_id', $challenge->google_id)->exists(), 403);
            $user->forceFill(['google_id' => $challenge->google_id, 'email_verified_at' => now()])->save();
            $challenge->update(['consumed_at' => now()]);

            return $user;
        }, 3);
        $request->session()->forget('google.link_browser');

        return $login->handle($request, $user);
    }

    private function validateChallenge(Request $request, GoogleLinkChallenge $challenge, string $token): void
    {
        $browser = $request->session()->get('google.link_browser');
        abort_unless(is_string($browser) && $challenge->consumed_at === null && $challenge->expires_at->isFuture()
            && hash_equals($challenge->token_hash, hash('sha256', $token)) && hash_equals($challenge->browser_hash, hash('sha256', $browser)), 403, 'This link has expired or must be opened in the browser where sign-in started.');
        $user = User::query()->find($challenge->user_id);
        abort_unless($user?->isActive() && $user->google_id === null && $user->auth_version === $challenge->auth_version && $user->email === $challenge->email, 403);
        abort_if($request->user() && $request->user()->id !== $user->id, 403);
    }
}

<?php

namespace App\Actions\Auth;

use App\Models\GoogleLinkChallenge;
use App\Models\User;
use App\Notifications\GoogleAccountLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BeginGoogleAccountLink
{
    public function handle(Request $request, User $user, string $googleId): Response
    {
        $token = Str::random(64);
        $browser = Str::random(64);
        $request->session()->put('google.link_browser', $browser);
        $challenge = DB::transaction(function () use ($user, $googleId, $token, $browser): GoogleLinkChallenge {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->isActive() && $user->google_id === null, 403);
            GoogleLinkChallenge::query()->where('user_id', $user->id)->delete();

            return GoogleLinkChallenge::query()->create(['user_id' => $user->id, 'email' => $user->email, 'google_id' => $googleId,
                'token_hash' => hash('sha256', $token), 'browser_hash' => hash('sha256', $browser), 'auth_version' => $user->auth_version,
                'expires_at' => now()->addMinutes(15)]);
        });
        $user->notify(new GoogleAccountLink($challenge->id, $token));

        return Inertia::render('auth/link-email', ['mode' => 'sent']);
    }
}

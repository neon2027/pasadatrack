<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

class AuthenticateGoogleUser
{
    public function handle(GoogleUser $googleUser, ?User $currentUser = null): User
    {
        $googleId = $googleUser->getId();
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $claims = $googleUser->getRaw();

        if (Validator::make(['id' => $googleId, 'email' => $email], [
            'id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ])->fails() || ($claims['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['google' => __('Google must provide a verified email address to sign in.')]);
        }

        $verificationRequired = false;

        $user = DB::transaction(function () use ($googleId, $email, $claims, $currentUser, &$verificationRequired): User {
            $user = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
            $emailUser = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            if ($currentUser && (($user && $user->id !== $currentUser->id)
                || ($currentUser->google_id !== null && $currentUser->google_id !== $googleId)
                || ($currentUser->google_id === null && Str::lower($currentUser->email) !== $email))) {
                throw ValidationException::withMessages(['google' => __('Choose the Google account already connected to your PasadaTrack account.')]);
            }

            if ($user && $emailUser && $emailUser->id !== $user->id) {
                throw ValidationException::withMessages(['google' => __('This email address belongs to another PasadaTrack account. Contact your administrator.')]);
            }

            $hostedDomain = $claims['hd'] ?? null;
            $googleOwnsEmail = Str::endsWith($email, '@gmail.com')
                || (is_string($hostedDomain) && $hostedDomain !== '');

            if (! $user) {
                if ($emailUser?->google_id !== null) {
                    throw ValidationException::withMessages(['google' => __('This email address is connected to a different Google account.')]);
                }

                $user = $emailUser;

                if (! $user || ! $user->isActive()) {
                    throw ValidationException::withMessages(['google' => __('Your account has not been provisioned or is suspended. Contact an administrator for access.')]);
                }
                if (! $googleOwnsEmail) {
                    return $user;
                }
            }
            if (! $user->isActive()) {
                throw ValidationException::withMessages(['google' => __('Your account has not been provisioned or is suspended. Contact an administrator for access.')]);
            }

            $emailChanged = $user->email !== $email;
            $verifiedAt = $googleOwnsEmail ? ($user->email_verified_at ?? now()) : ($emailChanged ? null : $user->email_verified_at);
            $verificationRequired = ! $googleOwnsEmail && $emailChanged;

            $user->forceFill([
                'google_id' => $googleId,
                'email' => $email,
                'email_verified_at' => $verifiedAt,
            ])->save();

            return $user;
        });

        if ($verificationRequired) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}

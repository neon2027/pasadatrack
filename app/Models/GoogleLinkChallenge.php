<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $google_id
 * @property string $token_hash
 * @property string $browser_hash
 * @property int $auth_version
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable(['user_id', 'email', 'google_id', 'token_hash', 'browser_hash', 'auth_version', 'expires_at', 'consumed_at'])]
#[Hidden(['google_id', 'token_hash', 'browser_hash'])]
class GoogleLinkChallenge extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'auth_version' => 'integer'];
    }
}

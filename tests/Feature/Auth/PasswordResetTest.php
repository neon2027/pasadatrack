<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('passwordResetEndpoints')]
    public function test_password_reset_endpoints_are_unavailable(string $method, string $path): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->call($method, $path, ['email' => $user->email, 'password' => 'new-password'])
            ->assertNotFound();

        $this->assertGuest();
        self::assertSame($originalPassword, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function passwordResetEndpoints(): array
    {
        return [
            'request page' => ['GET', '/forgot-password'],
            'request email' => ['POST', '/forgot-password'],
            'reset page' => ['GET', '/reset-password/token'],
            'reset password' => ['POST', '/reset-password'],
        ];
    }
}

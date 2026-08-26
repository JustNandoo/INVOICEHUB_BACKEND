<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_reset_link_to_a_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pemilik@toko.test']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'pemilik@toko.test'])
            ->assertOk()
            ->assertJsonPath('data.emailSent', true);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_an_unknown_email_gets_the_same_answer_so_accounts_cannot_be_enumerated(): void
    {
        Notification::fake();
        $known = User::factory()->create(['email' => 'pemilik@toko.test']);

        $terdaftar = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'pemilik@toko.test'])->assertOk();
        $asing = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'entah@siapa.test'])->assertOk();

        $this->assertSame($terdaftar->json('message'), $asing->json('message'));
        Notification::assertSentTo($known, ResetPasswordNotification::class);
        Notification::assertCount(1);
    }

    public function test_the_reset_link_points_at_the_frontend_page(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pemilik@toko.test']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'pemilik@toko.test'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user): bool {
            $url = $notification->toMail($user)->viewData['resetUrl'];

            return str_contains($url, '/reset-password')
                && str_contains($url, 'token=')
                && str_contains($url, urlencode($user->email));
        });
    }

    public function test_a_valid_link_passes_verification_and_an_unknown_one_does_not(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@toko.test']);
        $token = Password::createToken($user);

        $this->getJson('/api/v1/auth/password/verify?'.http_build_query([
            'token' => $token, 'email' => $user->email,
        ]))->assertOk()->assertJsonPath('data.valid', true);

        $this->getJson('/api/v1/auth/password/verify?'.http_build_query([
            'token' => 'token-karangan', 'email' => $user->email,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'RESET_TOKEN_INVALID');
    }

    public function test_it_resets_the_password_and_lets_the_user_log_in_again(): void
    {
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create(['email' => 'pemilik@toko.test', 'password' => 'PasswordLama1']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordBaru9',
            'passwordConfirmation' => 'PasswordBaru9',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('PasswordBaru9', $user->password));
        $this->assertNotNull($user->password_changed_at);
        Event::assertDispatched(PasswordReset::class);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'PasswordBaru9'])
            ->assertOk()->assertJsonPath('data.user.email', $user->email);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'PasswordLama1'])
            ->assertStatus(401)->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_resetting_signs_out_every_existing_session(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@toko.test']);
        $lama = $user->createToken('sesi-lama')->plainTextToken;
        $this->withToken($lama)->getJson('/api/v1/auth/me')->assertOk();

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'PasswordBaru9',
            'passwordConfirmation' => 'PasswordBaru9',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // Guard menyimpan user yang sudah diselesaikan pada request pertama, jadi
        // harus dilupakan agar request berikutnya benar-benar memeriksa ulang token.
        $this->app['auth']->forgetGuards();
        $this->withToken($lama)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@toko.test']);
        $token = Password::createToken($user);
        $payload = [
            'token' => $token, 'email' => $user->email,
            'password' => 'PasswordBaru9', 'passwordConfirmation' => 'PasswordBaru9',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/v1/auth/password/reset', [...$payload, 'password' => 'PasswordLain9', 'passwordConfirmation' => 'PasswordLain9'])
            ->assertStatus(422)->assertJsonPath('error.code', 'RESET_TOKEN_INVALID');

        $user->refresh();
        $this->assertTrue(Hash::check('PasswordBaru9', $user->password));
    }

    public function test_a_token_belonging_to_another_account_is_rejected(): void
    {
        $korban = User::factory()->create(['email' => 'korban@toko.test', 'password' => 'PasswordLama1']);
        $penyerang = User::factory()->create(['email' => 'penyerang@toko.test']);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => Password::createToken($penyerang),
            'email' => $korban->email,
            'password' => 'PasswordBaru9',
            'passwordConfirmation' => 'PasswordBaru9',
        ])->assertStatus(422)->assertJsonPath('error.code', 'RESET_TOKEN_INVALID');

        $this->assertTrue(Hash::check('PasswordLama1', $korban->refresh()->password));
    }

    public function test_a_weak_password_or_mismatched_confirmation_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'pemilik@toko.test']);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'lemah', 'passwordConfirmation' => 'lemah',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'PasswordBaru9', 'passwordConfirmation' => 'PasswordBeda9',
        ])->assertStatus(422)->assertJsonValidationErrors('passwordConfirmation');
    }
}

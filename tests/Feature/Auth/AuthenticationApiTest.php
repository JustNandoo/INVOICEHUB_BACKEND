<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->registrationPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.fullName', 'Budi Santoso')
            ->assertJsonPath('data.user.businessName', 'Toko Sejahtera')
            ->assertJsonPath('data.user.email', 'budi@example.com')
            ->assertJsonPath('data.emailVerificationRequired', true);

        $user = User::query()->where('email', 'budi@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Secure1234', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id, 'status' => 'active', 'source' => 'default',
        ]);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verification_email_uses_branded_html_and_plain_text_templates(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Budi Santoso']);
        $message = (new VerifyEmailNotification)->toMail($user);

        $this->assertSame('Verifikasi Email InvoiceHub', $message->subject);
        $this->assertSame([
            'html' => 'emails.auth.verify-email',
            'text' => 'emails.auth.verify-email-text',
        ], $message->view);

        $html = view('emails.auth.verify-email', $message->viewData)->render();
        $text = view('emails.auth.verify-email-text', $message->viewData)->render();

        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Verifikasi Email', $html);
        $this->assertStringContainsString('http://localhost', $text);
    }

    public function test_registration_rejects_weak_password_and_missing_terms_acceptance(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            ...$this->registrationPayload(),
            'password' => 'password',
            'termsAccepted' => false,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'termsAccepted']);
    }

    public function test_registration_normalizes_email_and_rejects_duplicates(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            ...$this->registrationPayload(),
            'email' => '  BUDI@EXAMPLE.COM ',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verified_user_can_login_and_access_profile(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'Secure1234',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'Secure1234',
            'remember' => true,
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonPath('data.user.email', 'budi@example.com');

        $accessToken = (string) $login->json('data.accessToken');
        $this->assertNotSame('', $accessToken);

        $this->withToken($accessToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'budi@example.com');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_unverified_user_cannot_receive_login_token(): void
    {
        User::factory()->unverified()->create([
            'email' => 'budi@example.com',
            'password' => 'Secure1234',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'Secure1234',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_uses_generic_error_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'Secure1234',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.com',
            'password' => 'WrongPassword123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau password salah.')
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_user_can_verify_email_using_a_temporary_signed_url(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_rejects_unsigned_url(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_can_be_resent_without_exposing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'budi@example.com']);

        $knownEmailResponse = $this->postJson('/api/v1/auth/email/resend', [
            'email' => 'budi@example.com',
        ]);
        $unknownEmailResponse = $this->postJson('/api/v1/auth/email/resend', [
            'email' => 'tidak-ada@example.com',
        ]);

        $knownEmailResponse->assertAccepted();
        $unknownEmailResponse
            ->assertAccepted()
            ->assertExactJson($knownEmailResponse->json());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_logout_revokes_current_access_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('test-device')->plainTextToken;

        $this->withToken($plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(): array
    {
        return [
            'fullName' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'businessName' => 'Toko Sejahtera',
            'password' => 'Secure1234',
            'termsAccepted' => true,
        ];
    }
}

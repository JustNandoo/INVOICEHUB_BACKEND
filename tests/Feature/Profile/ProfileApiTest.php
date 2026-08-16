<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_api_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $user = User::factory()->unverified()->create();

        $this->withToken($this->token($user))->getJson('/api/v1/profile')->assertForbidden();
    }

    public function test_user_can_read_and_update_profile_fields(): void
    {
        $user = User::factory()->create(['name' => 'Bella Hadid', 'business_name' => 'Dior']);
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/v1/profile')
            ->assertOk()->assertJsonPath('data.profile.fullName', 'Bella Hadid')
            ->assertJsonPath('data.profile.businessName', 'Dior')
            ->assertJsonPath('data.profile.photoUrl', null);

        $this->withToken($token)->patchJson('/api/v1/profile', [
            'fullName' => 'Bella Updated',
            'businessName' => 'InvoiceHub Store',
            'whatsapp' => '0812 3456 7890',
            'city' => 'Probolinggo',
            'businessType' => 'Reseller',
        ])->assertOk()
            ->assertJsonPath('data.profile.fullName', 'Bella Updated')
            ->assertJsonPath('data.profile.whatsapp', '+6281234567890')
            ->assertJsonPath('data.profile.city', 'Probolinggo')
            ->assertJsonPath('data.profile.businessType', 'reseller')
            ->assertJsonPath('data.emailChanged', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'whatsapp_normalized' => '6281234567890', 'business_type' => 'reseller',
        ]);
    }

    public function test_whatsapp_number_must_be_valid_and_unique_between_accounts(): void
    {
        User::factory()->create(['whatsapp' => '+6281211112222', 'whatsapp_normalized' => '6281211112222']);
        $user = User::factory()->create();
        $token = $this->token($user);

        $this->withToken($token)->patchJson('/api/v1/profile', ['whatsapp' => '123'])
            ->assertUnprocessable()->assertJsonValidationErrors('whatsapp');
        $this->withToken($token)->patchJson('/api/v1/profile', ['whatsapp' => '0812-1111-2222'])
            ->assertUnprocessable()->assertJsonValidationErrors('whatsapp');
    }

    public function test_email_change_requires_current_password_and_sends_new_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'CurrentPass123']);
        $token = $this->token($user);

        $this->withToken($token)->patchJson('/api/v1/profile', ['email' => 'new@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('currentPassword');
        $this->withToken($token)->patchJson('/api/v1/profile', [
            'email' => 'new@example.com', 'currentPassword' => 'WrongPass123',
        ])->assertUnprocessable()->assertJsonValidationErrors('currentPassword');

        $this->withToken($token)->patchJson('/api/v1/profile', [
            'email' => 'NEW@example.com', 'currentPassword' => 'CurrentPass123',
        ])->assertOk()->assertJsonPath('data.profile.email', 'new@example.com')
            ->assertJsonPath('data.profile.emailVerifiedAt', null)
            ->assertJsonPath('data.emailChanged', true)
            ->assertJsonPath('data.emailVerificationRequired', true)
            ->assertJsonPath('data.verificationEmailSent', true);

        $this->assertNull($user->refresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/profile')->assertForbidden();
    }

    public function test_user_can_upload_replace_and_delete_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $this->token($user);

        $first = $this->withToken($token)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('first.jpg', 300, 300),
        ], ['Accept' => 'application/json']);
        $first->assertOk()->assertJson(fn ($json) => $json->whereType('data.profile.photoUrl', 'string')->etc());
        $firstPath = $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->withToken($token)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('second.png', 400, 400),
        ], ['Accept' => 'application/json'])->assertOk();
        $secondPath = $user->refresh()->avatar_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->withToken($token)->deleteJson('/api/v1/profile/photo')
            ->assertOk()->assertJsonPath('data.profile.photoUrl', null);
        Storage::disk('public')->assertMissing($secondPath);
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_photo_upload_rejects_unsupported_or_oversized_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->withToken($this->token($user))->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_password_change_checks_current_password_hashes_new_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass123']);
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('other-device');

        $this->withToken($currentToken)->putJson('/api/v1/profile/password', [
            'currentPassword' => 'WrongPass123',
            'newPassword' => 'NewStrongPass456',
            'newPasswordConfirmation' => 'NewStrongPass456',
        ])->assertUnprocessable()->assertJsonValidationErrors('currentPassword');

        $this->withToken($currentToken)->putJson('/api/v1/profile/password', [
            'currentPassword' => 'CurrentPass123',
            'newPassword' => 'NewStrongPass456',
            'newPasswordConfirmation' => 'NewStrongPass456',
        ])->assertOk()->assertJsonPath('data.revokedOtherTokens', 1)
            ->assertJson(fn ($json) => $json->whereType('data.profile.passwordChangedAt', 'string')->etc());

        $this->assertTrue(Hash::check('NewStrongPass456', $user->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($currentToken)->getJson('/api/v1/profile')->assertOk();
    }

    public function test_profile_update_does_not_allow_role_or_verification_state_changes(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->withToken($this->token($user))->patchJson('/api/v1/profile', [
            'fullName' => 'Safe Name', 'role' => 'admin', 'emailVerifiedAt' => null,
        ])->assertOk()->assertJsonPath('data.profile.role', User::ROLE_USER);

        $user->refresh();
        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    private function token(User $user): string
    {
        return $user->createToken('profile-test')->plainTextToken;
    }
}

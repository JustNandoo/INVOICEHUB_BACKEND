<?php

namespace App\Services\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, emailChanged: bool}
     */
    public function update(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $model = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $attributes = [];
            $mapping = ['fullName' => 'name', 'businessName' => 'business_name', 'city' => 'city', 'businessType' => 'business_type'];
            foreach ($mapping as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $attributes[$column] = $this->nullableTrim($data[$input]);
                }
            }

            if (array_key_exists('whatsapp', $data)) {
                if ($data['whatsapp'] === null || trim((string) $data['whatsapp']) === '') {
                    $attributes['whatsapp'] = null;
                    $attributes['whatsapp_normalized'] = null;
                } else {
                    $phone = $this->normalizeWhatsapp((string) $data['whatsapp']);
                    $duplicate = User::query()->where('whatsapp_normalized', $phone['normalized'])
                        ->whereKeyNot($model->id)->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['whatsapp' => ['This WhatsApp number is already used by another account.']]);
                    }
                    $attributes['whatsapp'] = $phone['display'];
                    $attributes['whatsapp_normalized'] = $phone['normalized'];
                }
            }

            $emailChanged = array_key_exists('email', $data) && $data['email'] !== $model->email;
            if ($emailChanged) {
                $attributes['email'] = $data['email'];
                $attributes['email_verified_at'] = null;
            }

            $model->forceFill($attributes)->save();

            return ['user' => $model->refresh(), 'emailChanged' => $emailChanged];
        }, 3);
    }

    public function updatePhoto(User $user, UploadedFile $photo): User
    {
        $oldPath = $user->avatar_path;
        $path = $photo->storeAs(
            'profile-photos/'.$user->id,
            Str::uuid().'.'.$photo->guessExtension(),
            'public',
        );

        if (! $path) {
            throw ValidationException::withMessages(['photo' => ['Profile photo could not be stored.']]);
        }

        try {
            $user->update(['avatar_path' => $path]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $user->refresh();
    }

    public function deletePhoto(User $user): User
    {
        $path = $user->avatar_path;
        $user->update(['avatar_path' => null]);
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return $user->refresh();
    }

    /** @return array{user: User, revokedOtherTokens: int} */
    public function changePassword(User $user, string $currentPassword, string $newPassword, ?int $currentTokenId): array
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => ['Current password is incorrect.']]);
        }

        return DB::transaction(function () use ($user, $newPassword, $currentTokenId): array {
            $model = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model->update(['password' => $newPassword, 'password_changed_at' => now()]);
            $tokens = $model->tokens();
            if ($currentTokenId !== null) {
                $tokens->whereKeyNot($currentTokenId);
            }
            $revoked = $tokens->delete();

            return ['user' => $model->refresh(), 'revokedOtherTokens' => $revoked];
        }, 3);
    }

    /** @return array{display: string, normalized: string} */
    private function normalizeWhatsapp(string $value): array
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if (str_starts_with((string) $digits, '0')) {
            $digits = '62'.substr((string) $digits, 1);
        } elseif (str_starts_with((string) $digits, '8')) {
            $digits = '62'.$digits;
        }
        if (! $digits || ! preg_match('/^[1-9][0-9]{9,14}$/', $digits)) {
            throw ValidationException::withMessages(['whatsapp' => ['Masukkan nomor WhatsApp yang valid beserta kode negaranya.']]);
        }

        return ['display' => '+'.$digits, 'normalized' => $digits];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}

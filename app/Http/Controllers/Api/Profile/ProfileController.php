<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\Profile\UpdateProfileRequest;
use App\Http\Requests\Api\Profile\UploadProfilePhotoRequest;
use App\Http\Resources\Api\UserResource;
use App\Services\Profile\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'profile' => (new UserResource($request->user()))->resolve($request),
        ]]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $result = $this->profiles->update($request->user(), $request->validated());
        $verificationEmailSent = null;

        if ($result['emailChanged']) {
            $verificationEmailSent = true;
            try {
                $result['user']->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                report($exception);
                $verificationEmailSent = false;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $result['emailChanged']
                ? 'Profil diperbarui. Silakan verifikasi alamat email baru Anda.'
                : 'Profil berhasil diperbarui.',
            'data' => [
                'profile' => (new UserResource($result['user']))->resolve($request),
                'emailChanged' => $result['emailChanged'],
                'emailVerificationRequired' => $result['emailChanged'],
                'verificationEmailSent' => $verificationEmailSent,
            ],
        ]);
    }

    public function uploadPhoto(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $this->profiles->updatePhoto($request->user(), $request->file('photo'));

        return response()->json([
            'success' => true, 'message' => 'Foto profil berhasil diperbarui.',
            'data' => ['profile' => (new UserResource($user))->resolve($request)],
        ]);
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $this->profiles->deletePhoto($request->user());

        return response()->json([
            'success' => true, 'message' => 'Foto profil berhasil dihapus.',
            'data' => ['profile' => (new UserResource($user))->resolve($request)],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();
        $result = $this->profiles->changePassword(
            $request->user(),
            $request->validated('currentPassword'),
            $request->validated('newPassword'),
            $accessToken instanceof PersonalAccessToken ? $accessToken->id : null,
        );

        return response()->json([
            'success' => true, 'message' => 'Kata sandi berhasil diperbarui.',
            'data' => [
                'profile' => (new UserResource($result['user']))->resolve($request),
                'revokedOtherTokens' => $result['revokedOtherTokens'],
            ],
        ]);
    }
}

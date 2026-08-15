<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authentication,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authentication->register($request->validated());

        $verificationEmailSent = true;

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);
            $verificationEmailSent = false;
        }

        return response()->json([
            'success' => true,
            'message' => $verificationEmailSent
                ? 'Registrasi berhasil. Silakan periksa email untuk melakukan verifikasi.'
                : 'Registrasi berhasil, tetapi email verifikasi belum dapat dikirim. Silakan kirim ulang verifikasi.',
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'emailVerificationRequired' => true,
                'verificationEmailSent' => $verificationEmailSent,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authentication->authenticate(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
                'error' => ['code' => 'INVALID_CREDENTIALS'],
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum diverifikasi. Silakan periksa email Anda.',
                'error' => ['code' => 'EMAIL_NOT_VERIFIED'],
            ], 403);
        }

        $token = $this->authentication->issueToken(
            $user,
            $request->boolean('remember'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'tokenType' => 'Bearer',
                'accessToken' => $token['accessToken'],
                'expiresAt' => $token['expiresAt']->toIso8601String(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authentication->revokeCurrentToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }
}

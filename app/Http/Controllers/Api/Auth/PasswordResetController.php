<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Lupa password.
 *
 * Alurnya tiga langkah: minta tautan lewat email, tautannya diverifikasi, baru
 * password diganti. Token dibuat dan diperiksa oleh broker bawaan Laravel, jadi
 * masa berlaku, hashing, dan throttle-nya sudah teruji.
 */
class PasswordResetController extends Controller
{
    /**
     * Mengirim tautan reset.
     *
     * Balasannya selalu sama apa pun hasilnya. Membedakan "email terdaftar" dan
     * "tidak terdaftar" akan membuat endpoint ini bisa dipakai memetakan siapa saja
     * yang punya akun di InvoiceHub.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => true,
            'message' => 'Bila email tersebut terdaftar, tautan untuk mengatur ulang password sudah kami kirim. Periksa juga folder spam.',
            'data' => ['emailSent' => $status === Password::RESET_LINK_SENT],
        ]);
    }

    /**
     * Memeriksa keabsahan tautan sebelum formulir password baru ditampilkan, supaya
     * pengguna tidak mengetik password baru untuk tautan yang ternyata kedaluwarsa.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::query()->where('email', mb_strtolower(trim($validated['email'])))->first();
        $valid = $user !== null && Password::getRepository()->exists($user, $validated['token']);

        if (! $valid) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan tidak dikenali atau sudah kedaluwarsa. Silakan minta tautan baru.',
                'error' => ['code' => 'RESET_TOKEN_INVALID'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['valid' => true, 'email' => $user->email],
        ]);
    }

    /** Menetapkan password baru dan mengakhiri seluruh sesi lama. */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            [
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'password_confirmation' => $request->validated('passwordConfirmation'),
                'token' => $request->validated('token'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                // Password lama mungkin sudah bocor, jadi semua sesi yang masih
                // memegang token lama ikut diputus.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan tidak dikenali atau sudah kedaluwarsa. Silakan minta tautan baru.',
                'error' => ['code' => 'RESET_TOKEN_INVALID'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Silakan masuk dengan password baru Anda.',
        ]);
    }
}

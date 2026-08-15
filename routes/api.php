<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Blog\BlogController;
use App\Http\Controllers\Api\Blog\BlogManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin/blogs')
    ->middleware(['auth:sanctum', 'verified', 'can:manage-blog', 'throttle:blog-management'])
    ->group(function (): void {
        Route::get('/', [BlogManagementController::class, 'index']);
        Route::post('/', [BlogManagementController::class, 'store']);
        Route::get('/{blogPost}', [BlogManagementController::class, 'show'])->whereNumber('blogPost');
        Route::match(['put', 'patch'], '/{blogPost}', [BlogManagementController::class, 'update'])->whereNumber('blogPost');
        Route::delete('/{blogPost}', [BlogManagementController::class, 'destroy'])->whereNumber('blogPost');
    });

Route::prefix('v1/blogs')
    ->middleware('throttle:blog-public')
    ->group(function (): void {
        Route::get('/', [BlogController::class, 'index']);
        Route::get('/{slug}', [BlogController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    });

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');

    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:auth-verification-resend');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->whereNumber('id')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->middleware('verified');
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

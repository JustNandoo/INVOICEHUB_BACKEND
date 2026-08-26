<?php

namespace App\Providers;

use App\Exceptions\Ai\AiProviderException;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\GeminiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, function (): AiProvider {
            $provider = (string) config('ai.provider', 'gemini');
            $config = (array) config("ai.providers.{$provider}", []);

            return match ($provider) {
                'gemini' => new GeminiProvider($config),
                default => throw new AiProviderException("Provider AI '{$provider}' tidak dikenal.", 'misconfigured'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-blog', fn (User $user): bool => $user->isAdmin());

        $this->configurePasswordRules();
        $this->configureRateLimiters();
    }

    private function configurePasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(8)
                ->mixedCase()
                ->numbers();

            return $this->app->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('invoice-management', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('customer-management', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('profile-management', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('profile-password', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('reconciliation-management', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('tax-report-management', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('notification-management', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('ai-management', fn (Request $request): array => [
            Limit::perMinute(20)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perDay(200)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
        ]);

        // Setiap panggilan membuat order baru di Midtrans, jadi dibatasi lebih ketat
        // daripada endpoint baca biasa.
        RateLimiter::for('subscription-checkout', fn (Request $request): array => [
            Limit::perMinute(6)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perDay(50)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())),
        ]);

        RateLimiter::for('blog-public', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->ip()));

        RateLimiter::for('blog-management', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('auth-register', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip()));

        RateLimiter::for('auth-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->emailAndIpKey($request)));

        // Dibatasi per email sekaligus per IP: mencegah satu akun dibanjiri email
        // reset, sekaligus mencegah satu IP memindai banyak alamat sekaligus.
        RateLimiter::for('auth-password-forgot', fn (Request $request): array => [
            Limit::perMinute(3)->by($this->emailAndIpKey($request)),
            Limit::perHour(10)->by($request->ip()),
        ]);

        RateLimiter::for('auth-password-reset', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->ip()));

        RateLimiter::for('auth-verification-resend', fn (Request $request): array => [
            Limit::perMinute(3)->by($this->emailAndIpKey($request)),
            Limit::perHour(10)->by($request->ip()),
        ]);
    }

    private function emailAndIpKey(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();
    }
}

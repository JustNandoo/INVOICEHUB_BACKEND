<?php

namespace App\Providers;

use App\Models\User;
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
        //
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

        RateLimiter::for('blog-public', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->ip()));

        RateLimiter::for('blog-management', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('auth-register', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip()));

        RateLimiter::for('auth-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->emailAndIpKey($request)));

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

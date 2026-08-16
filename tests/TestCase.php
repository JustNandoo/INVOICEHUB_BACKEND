<?php

namespace Tests;

use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function activatePlan(User $user, string $plan = 'pro'): UserSubscription
    {
        return $this->app->make(SubscriptionService::class)->activatePlan(
            $user, $plan, 'test', now()->toImmutable()->addMonth(),
        );
    }
}

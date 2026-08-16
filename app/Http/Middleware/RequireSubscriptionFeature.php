<?php

namespace App\Http\Middleware;

use App\Services\Subscription\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSubscriptionFeature
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $this->entitlements->require($request->user(), $feature);

        return $next($request);
    }
}

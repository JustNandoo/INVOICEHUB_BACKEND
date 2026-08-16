<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\SearchCustomersRequest;
use App\Http\Resources\Api\CustomerSearchResource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CustomerSearchController extends Controller
{
    public function __invoke(SearchCustomersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $customers = Customer::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('email', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('whatsapp', "%{$search}%", caseSensitive: false);
                });
            })
            ->orderBy('name')
            ->limit((int) $filters['limit'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => CustomerSearchResource::collection($customers)->resolve($request),
            ],
        ]);
    }
}

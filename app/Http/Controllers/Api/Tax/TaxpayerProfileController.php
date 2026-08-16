<?php

namespace App\Http\Controllers\Api\Tax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tax\UpdateTaxpayerProfileRequest;
use App\Http\Resources\Api\TaxpayerProfileResource;
use App\Models\TaxpayerProfile;
use App\Services\Tax\TaxpayerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxpayerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = TaxpayerProfile::query()->with('taxRule')->where('user_id', $request->user()->id)->first();

        return response()->json(['success' => true, 'data' => [
            'isConfigured' => $profile !== null,
            'taxProfile' => $profile ? (new TaxpayerProfileResource($profile))->resolve($request) : null,
        ]]);
    }

    public function update(UpdateTaxpayerProfileRequest $request, TaxpayerProfileService $service): JsonResponse
    {
        $profile = $service->update($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil pajak berhasil disimpan.',
            'data' => ['taxProfile' => (new TaxpayerProfileResource($profile))->resolve($request)],
        ]);
    }
}

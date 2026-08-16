<?php

namespace App\Http\Controllers\Api\Reconciliation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BankAccountResource;
use App\Models\BankAccount;
use App\Services\Reconciliation\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = BankAccount::query()->where('user_id', $request->user()->id)->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => [
            'accounts' => BankAccountResource::collection($accounts)->resolve($request),
        ]]);
    }

    public function sync(Request $request, int $bankAccount, BankAccountService $service): JsonResponse
    {
        $account = $this->owned($request, $bankAccount);
        $result = $service->sync($account);

        return response()->json([
            'success' => true,
            'message' => 'Sinkronisasi rekening selesai.',
            'data' => [
                'account' => (new BankAccountResource($result['account']))->resolve($request),
                'importedTransactions' => $result['importedTransactions'],
            ],
        ]);
    }

    private function owned(Request $request, int $id): BankAccount
    {
        return BankAccount::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}

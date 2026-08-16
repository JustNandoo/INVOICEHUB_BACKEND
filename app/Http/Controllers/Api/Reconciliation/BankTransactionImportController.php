<?php

namespace App\Http\Controllers\Api\Reconciliation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reconciliation\ImportBankTransactionsRequest;
use App\Http\Resources\Api\BankTransactionImportResource;
use App\Models\BankAccount;
use App\Models\BankTransactionImport;
use App\Services\Reconciliation\BankTransactionImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class BankTransactionImportController extends Controller
{
    public function store(ImportBankTransactionsRequest $request, BankTransactionImportService $service): JsonResponse
    {
        $account = BankAccount::query()->where('user_id', $request->user()->id)->findOrFail($request->integer('bankAccountId'));
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $import = $service->import($request->user(), $account, $file);

        return response()->json([
            'success' => true, 'message' => 'File mutasi selesai diproses.',
            'data' => ['import' => (new BankTransactionImportResource($import))->resolve($request)],
        ], 201);
    }

    public function show(Request $request, int $import): JsonResponse
    {
        $model = BankTransactionImport::query()->where('user_id', $request->user()->id)->findOrFail($import);

        return response()->json(['success' => true, 'data' => [
            'import' => (new BankTransactionImportResource($model))->resolve($request),
        ]]);
    }
}

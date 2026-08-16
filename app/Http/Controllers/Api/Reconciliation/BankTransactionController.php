<?php

namespace App\Http\Controllers\Api\Reconciliation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reconciliation\IgnoreBankTransactionRequest;
use App\Http\Requests\Api\Reconciliation\ListBankTransactionsRequest;
use App\Http\Requests\Api\Reconciliation\RejectCandidateRequest;
use App\Http\Resources\Api\BankTransactionResource;
use App\Http\Resources\Api\ReconciliationSuggestionResource;
use App\Models\BankTransaction;
use App\Services\Reconciliation\ReconciliationMatchingService;
use App\Services\Reconciliation\ReconciliationQueryService;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    public function index(ListBankTransactionsRequest $request, ReconciliationQueryService $queries): JsonResponse
    {
        $paginator = $queries->transactions($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => [
            'transactions' => BankTransactionResource::collection($paginator->items())->resolve($request),
            'pagination' => $this->pagination($paginator),
        ]]);
    }

    public function show(Request $request, int $bankTransaction): JsonResponse
    {
        $transaction = $this->owned($request, $bankTransaction)->load('bankAccount');

        return response()->json(['success' => true, 'data' => [
            'transaction' => (new BankTransactionResource($transaction))->resolve($request),
        ]]);
    }

    public function candidates(Request $request, int $bankTransaction, ReconciliationMatchingService $matching): JsonResponse
    {
        $transaction = $this->owned($request, $bankTransaction);
        $candidates = $matching->candidates($transaction);

        return response()->json(['success' => true, 'data' => [
            'transactionId' => $transaction->id,
            'candidates' => ReconciliationSuggestionResource::collection($candidates)->resolve($request),
        ]]);
    }

    public function ignore(IgnoreBankTransactionRequest $request, int $bankTransaction, ReconciliationService $service): JsonResponse
    {
        $transaction = $service->ignore($this->owned($request, $bankTransaction), $request->validated('reason'));

        return response()->json(['success' => true, 'message' => 'Mutasi berhasil diabaikan.', 'data' => [
            'transaction' => (new BankTransactionResource($transaction))->resolve($request),
        ]]);
    }

    public function rejectCandidate(RejectCandidateRequest $request, int $bankTransaction, ReconciliationService $service): JsonResponse
    {
        $data = $request->validated();
        $suggestion = $service->rejectCandidate(
            $this->owned($request, $bankTransaction),
            (int) $data['invoiceId'],
            $data['reason'] ?? null,
        );

        return response()->json(['success' => true, 'message' => 'Kandidat invoice ditolak.', 'data' => [
            'candidate' => (new ReconciliationSuggestionResource($suggestion))->resolve($request),
        ]]);
    }

    private function owned(Request $request, int $id): BankTransaction
    {
        return BankTransaction::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function pagination($paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
            'lastPage' => $paginator->lastPage(), 'total' => $paginator->total(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
            'previousPageUrl' => $paginator->previousPageUrl(), 'nextPageUrl' => $paginator->nextPageUrl(),
        ];
    }
}

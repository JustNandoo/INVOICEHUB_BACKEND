<?php

namespace App\Services\Ai\Features;

use App\Enums\Ai\AiFeature;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiProviderException;
use App\Exceptions\Ai\AiValidationException;
use App\Models\AiRun;
use App\Models\BankTransaction;
use App\Models\ReconciliationSuggestion;
use App\Models\User;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Prompts\ReconciliationPrompt;
use App\Services\Ai\Sanitizer;
use App\Services\Ai\Schemas\ReconciliationSchema;
use App\Services\Ai\Support\AiPrompt;
use App\Services\Reconciliation\ReconciliationMatchingService;
use App\Services\Subscription\EntitlementService;
use Illuminate\Support\Collection;

class ReconciliationAiService
{
    /** Below this confidence the recommendation is never presented as ready to confirm. */
    private const REVIEW_THRESHOLD = 70;

    public function __construct(
        private readonly ReconciliationMatchingService $matching,
        private readonly AiOrchestrator $orchestrator,
        private readonly EntitlementService $entitlements,
        private readonly Sanitizer $sanitizer,
    ) {}

    /**
     * Rank the deterministic candidates with AI. Laravel always produces the candidate
     * list first; AI may only reorder and explain it.
     *
     * @return array{candidates: Collection<int, ReconciliationSuggestion>, analysis: array<string, mixed>}
     */
    public function analyze(User $user, BankTransaction $transaction): array
    {
        $candidates = $this->matching->candidates($transaction);
        $maxCandidates = $this->entitlements->subscription($user)->plan->limit('aiMaxCandidates') ?? 3;
        $shortlist = $candidates->take(max(0, min(3, (int) $maxCandidates)))->values();

        if ($shortlist->isEmpty()) {
            return [
                'candidates' => $candidates,
                'analysis' => $this->fallback($candidates, 'no_candidates'),
            ];
        }

        $allowedIds = $shortlist->pluck('invoice_id')->map(fn ($id): int => (int) $id)->all();
        $payload = $this->context($transaction, $shortlist);

        try {
            $run = $this->orchestrator->runStructured(
                $user,
                AiFeature::ReconciliationAnalysis,
                new AiPrompt(
                    systemInstruction: ReconciliationPrompt::system(),
                    userContent: ReconciliationPrompt::user($payload),
                    schema: ReconciliationSchema::definition(),
                    hashPayload: $payload,
                ),
                fn (array $output): array => $this->validate($output, $allowedIds, $shortlist),
            );
        } catch (AiDisabledException|AiProviderException|AiValidationException $exception) {
            // Core reconciliation must keep working when AI is unavailable or misbehaves.
            return [
                'candidates' => $candidates,
                'analysis' => $this->fallback($candidates, $this->reasonFor($exception)),
            ];
        }

        /** @var array<string, mixed> $analysis */
        $analysis = $run->structured_output ?? [];
        $this->persist($run, $shortlist, $analysis);

        return [
            'candidates' => $candidates->fresh() ?? $candidates,
            'analysis' => [...$analysis, 'aiRunId' => $run->id, 'aiAvailable' => true, 'source' => 'ai'],
        ];
    }

    /**
     * Every rule here is a hard gate. Anything that fails is rejected so the
     * orchestrator can retry, and is never stored as a successful result.
     *
     * @param  array<string, mixed>  $output
     * @param  list<int>  $allowedIds
     * @param  Collection<int, ReconciliationSuggestion>  $shortlist
     * @return array<string, mixed>
     */
    private function validate(array $output, array $allowedIds, Collection $shortlist): array
    {
        $violations = [];

        $invoiceId = $output['recommendedInvoiceId'] ?? null;
        if ($invoiceId !== null && ! in_array((int) $invoiceId, $allowedIds, true)) {
            $violations[] = 'recommendedInvoiceId di luar daftar kandidat yang diberikan.';
        }

        $confidence = $output['confidence'] ?? null;
        if (! is_int($confidence) || $confidence < 0 || $confidence > 100) {
            $violations[] = 'confidence harus bilangan bulat 0-100.';
        }

        $action = $output['recommendedAction'] ?? null;
        if (! is_string($action) || ! in_array($action, ReconciliationSchema::ACTIONS, true)) {
            $violations[] = 'recommendedAction di luar daftar aksi yang diizinkan.';
        }

        $reasons = $output['reasons'] ?? null;
        if (! is_array($reasons) || $reasons === []) {
            $violations[] = 'reasons wajib berisi minimal satu alasan.';
            $reasons = [];
        }

        if (! is_bool($output['requiresReview'] ?? null)) {
            $violations[] = 'requiresReview harus boolean.';
        }

        $fee = $output['inferredFee'] ?? null;
        if ($fee !== null && (! is_int($fee) || $fee < 0)) {
            $violations[] = 'inferredFee harus bilangan bulat non-negatif atau null.';
        }

        if ($violations !== []) {
            throw new AiValidationException($violations);
        }

        $reasons = collect($reasons)
            ->filter(fn ($reason): bool => is_string($reason) && trim($reason) !== '')
            ->map(fn (string $reason): string => (string) $this->sanitizer->text($reason, ReconciliationSchema::MAX_REASON_LENGTH))
            ->take(ReconciliationSchema::MAX_REASONS)
            ->values()
            ->all();

        $chosen = $invoiceId === null
            ? null
            : $shortlist->firstWhere('invoice_id', (int) $invoiceId);

        // The fee Laravel computed wins. A mismatch means the model reasoned about
        // numbers we did not give it, so a human has to look at it.
        $feeMismatch = $chosen !== null && $fee !== null && $fee !== (int) $chosen->difference_amount;

        $requiresReview = (bool) $output['requiresReview']
            || $invoiceId === null
            || (int) $confidence < self::REVIEW_THRESHOLD
            || $feeMismatch;

        return [
            'recommendedInvoiceId' => $invoiceId === null ? null : (int) $invoiceId,
            'confidence' => (int) $confidence,
            'reasons' => $reasons,
            'inferredFee' => $fee === null ? null : (int) $fee,
            'verifiedFee' => $chosen?->difference_amount === null ? null : (int) $chosen->difference_amount,
            'feeMismatch' => $feeMismatch,
            'recommendedAction' => $action,
            'requiresReview' => $requiresReview,
        ];
    }

    /**
     * @param  Collection<int, ReconciliationSuggestion>  $shortlist
     * @param  array<string, mixed>  $analysis
     */
    private function persist(AiRun $run, Collection $shortlist, array $analysis): void
    {
        $recommendedId = $analysis['recommendedInvoiceId'] ?? null;
        $nextRank = 2;

        foreach ($shortlist as $suggestion) {
            $isRecommended = $recommendedId !== null && (int) $suggestion->invoice_id === (int) $recommendedId;

            $suggestion->update([
                'ai_run_id' => $run->id,
                'ai_rank' => $isRecommended ? 1 : $nextRank++,
                'ai_confidence' => $isRecommended ? (int) $analysis['confidence'] : null,
                'ai_reasons' => $isRecommended ? $analysis['reasons'] : null,
                'ai_requires_review' => (bool) ($analysis['requiresReview'] ?? true),
            ]);
        }
    }

    /**
     * @param  Collection<int, ReconciliationSuggestion>  $candidates
     * @return array<string, mixed>
     */
    private function fallback(Collection $candidates, string $reason): array
    {
        $top = $candidates->first();

        return [
            'recommendedInvoiceId' => $top?->invoice_id === null ? null : (int) $top->invoice_id,
            'confidence' => $top === null ? 0 : (int) $top->score,
            'reasons' => $top?->reasons ?? [],
            'inferredFee' => null,
            'verifiedFee' => $top?->difference_amount === null ? null : (int) $top->difference_amount,
            'feeMismatch' => false,
            'recommendedAction' => 'review_manually',
            'requiresReview' => true,
            'aiRunId' => null,
            'aiAvailable' => false,
            'source' => 'rules',
            'unavailableReason' => $reason,
        ];
    }

    private function reasonFor(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AiDisabledException => $exception->reason,
            $exception instanceof AiProviderException => $exception->errorCode,
            default => 'invalid_output',
        };
    }

    /**
     * Only the fields the model actually needs. No email, WhatsApp, address or full
     * account number ever leaves the backend.
     *
     * @param  Collection<int, ReconciliationSuggestion>  $shortlist
     * @return array<string, mixed>
     */
    private function context(BankTransaction $transaction, Collection $shortlist): array
    {
        $transaction->loadMissing('bankAccount');

        return [
            'transaction' => [
                'amount' => (int) $transaction->amount,
                'transactionAt' => $transaction->transaction_at?->toIso8601String(),
                'senderName' => $this->sanitizer->name($transaction->sender_name),
                'description' => $this->sanitizer->text($transaction->description),
                'reference' => $this->sanitizer->text($transaction->reference, 80),
                'bankCode' => $transaction->bankAccount?->bank_code,
                'accountMasked' => $this->sanitizer->maskAccount($transaction->bankAccount?->account_number_last_four),
            ],
            'candidates' => $shortlist->map(fn (ReconciliationSuggestion $suggestion): array => [
                'id' => (int) $suggestion->invoice_id,
                'invoiceNumber' => $suggestion->invoice->number,
                'customerName' => $this->sanitizer->name($suggestion->invoice->customer_name),
                'totalAmount' => (int) $suggestion->invoice->total_amount,
                'balanceDue' => (int) $suggestion->invoice->balance_due,
                'issueDate' => $suggestion->invoice->issue_date?->format('Y-m-d'),
                'dueDate' => $suggestion->invoice->due_date?->format('Y-m-d'),
                'ruleScore' => (int) $suggestion->score,
                'ruleDifferenceAmount' => (int) $suggestion->difference_amount,
                'ruleDifferenceType' => $suggestion->difference_type,
                'ruleReasons' => $suggestion->reasons,
            ])->all(),
            'context' => [
                'today' => now()->toDateString(),
                'commonBankFees' => [1500, 2500, 4000, 6500],
            ],
        ];
    }
}

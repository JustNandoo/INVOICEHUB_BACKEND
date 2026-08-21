<?php

namespace App\Services\Ai\Features;

use App\Enums\Ai\AiFeature;
use App\Enums\Ai\AiRunStatus;
use App\Enums\Ai\InsightAction;
use App\Enums\Ai\InsightSeverity;
use App\Enums\Ai\InsightType;
use App\Exceptions\Ai\AiValidationException;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Prompts\FinancialInsightPrompt;
use App\Services\Ai\Sanitizer;
use App\Services\Ai\Schemas\FinancialInsightSchema;
use App\Services\Ai\Support\AiPrompt;
use App\Services\Customer\CustomerQueryService;
use App\Services\Invoice\InvoiceQueryService;
use App\Services\Reconciliation\ReconciliationQueryService;
use App\Services\Subscription\EntitlementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialInsightService
{
    public function __construct(
        private readonly AiOrchestrator $orchestrator,
        private readonly EntitlementService $entitlements,
        private readonly InvoiceQueryService $invoices,
        private readonly CustomerQueryService $customers,
        private readonly ReconciliationQueryService $reconciliations,
        private readonly Sanitizer $sanitizer,
    ) {}

    /**
     * Reads stored insights only. This is what the dashboard calls, and it must never
     * reach the AI provider no matter how often it is refreshed.
     *
     * @return Collection<int, AiInsight>
     */
    public function visible(User $user): Collection
    {
        return AiInsight::query()
            ->where('user_id', $user->id)
            ->visible()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Generate a fresh batch. Called from the queue, never straight from a web request.
     *
     * @return Collection<int, AiInsight>
     */
    public function generate(User $user): Collection
    {
        $this->entitlements->require($user, 'ai.insights');

        $max = $this->maxInsights($user);
        $metrics = $this->metrics($user);
        $allowedNumbers = $this->numbersIn($metrics);

        $run = $this->orchestrator->runStructured(
            $user,
            AiFeature::FinancialInsight,
            new AiPrompt(
                systemInstruction: FinancialInsightPrompt::system($max),
                userContent: FinancialInsightPrompt::user($metrics),
                schema: FinancialInsightSchema::definition(),
                hashPayload: $metrics,
            ),
            fn (array $output): array => $this->validate($output, $allowedNumbers, $max),
        );

        return $this->persist($user, $run);
    }

    /**
     * Whether enough time has passed for this plan to earn a fresh batch.
     */
    public function isDue(User $user): bool
    {
        $intervalDays = $this->entitlements->subscription($user)->plan->limit('aiInsightIntervalDays') ?? 1;
        $last = $this->lastGeneratedAt($user);

        return $last === null || $last->lte(now()->subDays(max(1, $intervalDays)));
    }

    public function lastGeneratedAt(User $user): ?Carbon
    {
        return AiRun::query()
            ->where('user_id', $user->id)
            ->where('feature', AiFeature::FinancialInsight->value)
            ->where('status', AiRunStatus::Succeeded->value)
            ->latest('id')
            ->value('created_at');
    }

    public function maxInsights(User $user): int
    {
        return max(1, min(5, (int) ($this->entitlements->subscription($user)->plan->limit('aiMaxInsights') ?? 3)));
    }

    /**
     * Aggregate figures only. The ledger itself never leaves the backend, and every number
     * the AI is allowed to quote is in here.
     *
     * @return array<string, mixed>
     */
    public function metrics(User $user): array
    {
        $now = now();
        $thisWeek = $this->receivedBetween($user, $now->copy()->startOfWeek(), $now);
        $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
        $lastWeek = $this->receivedBetween($user, $lastWeekStart, $lastWeekStart->copy()->endOfWeek());

        $metrics = [
            'period' => [
                'today' => $now->toDateString(),
                'month' => (int) $now->format('n'),
                'year' => (int) $now->format('Y'),
            ],
            'invoices' => $this->invoices->summary($user),
            'customers' => $this->customers->summary($user, (int) $now->format('Y'), (int) $now->format('n')),
            'cashflow' => [
                'receivedThisWeek' => $thisWeek,
                'receivedLastWeek' => $lastWeek,
                'receivedChangePercent' => $lastWeek === 0 ? 0 : (int) round((($thisWeek - $lastWeek) / $lastWeek) * 100),
                'oldestOverdueDays' => $this->oldestOverdueDays($user),
            ],
        ];

        // Only surface reconciliation numbers to plans that actually have the module.
        if ($this->entitlements->has($user, 'reconciliation.automatic')) {
            $metrics['reconciliation'] = $this->reconciliations->summary($user);
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  list<int>  $allowedNumbers
     * @return array<string, mixed>
     */
    private function validate(array $output, array $allowedNumbers, int $max): array
    {
        $insights = $output['insights'] ?? null;

        if (! is_array($insights) || $insights === []) {
            throw new AiValidationException(['insights wajib berisi minimal satu item.']);
        }

        $clean = [];
        $violations = [];

        foreach (array_slice($insights, 0, $max) as $index => $insight) {
            if (! is_array($insight)) {
                $violations[] = "insights[{$index}] bukan objek.";

                continue;
            }

            $type = InsightType::tryFrom((string) ($insight['type'] ?? ''));
            $severity = InsightSeverity::tryFrom((string) ($insight['severity'] ?? ''));
            $action = InsightAction::tryFrom((string) ($insight['recommendedAction'] ?? ''));

            if ($type === null || $severity === null || $action === null) {
                $violations[] = "insights[{$index}] memakai type, severity, atau recommendedAction di luar daftar.";

                continue;
            }

            $title = $this->sanitizer->text($insight['title'] ?? null, FinancialInsightSchema::MAX_TITLE_LENGTH);
            $summary = $this->sanitizer->text($insight['summary'] ?? null, FinancialInsightSchema::MAX_SUMMARY_LENGTH);

            if ($title === null || $summary === null) {
                $violations[] = "insights[{$index}] tidak punya title atau summary.";

                continue;
            }

            $evidence = $this->validateEvidence($insight['evidence'] ?? null, $allowedNumbers, $index, $violations);

            if ($evidence === null) {
                continue;
            }

            $clean[] = [
                'type' => $type->value,
                'severity' => $severity->value,
                'title' => $title,
                'summary' => $summary,
                'evidence' => $evidence,
                'recommendedAction' => $action->value,
                'actionUrl' => $action->url(),
            ];
        }

        if ($clean === []) {
            throw new AiValidationException($violations === [] ? ['Tidak ada insight yang lolos validasi.'] : $violations);
        }

        return ['insights' => $clean];
    }

    /**
     * The anti-hallucination gate: an insight may only quote numbers Laravel supplied.
     *
     * @param  list<int>  $allowedNumbers
     * @param  list<string>  $violations
     * @return list<array{label: string, value: int}>|null
     */
    private function validateEvidence(mixed $evidence, array $allowedNumbers, int $index, array &$violations): ?array
    {
        if (! is_array($evidence) || $evidence === []) {
            $violations[] = "insights[{$index}].evidence kosong.";

            return null;
        }

        $clean = [];

        foreach (array_slice($evidence, 0, FinancialInsightSchema::MAX_EVIDENCE) as $item) {
            if (! is_array($item) || ! is_int($item['value'] ?? null)) {
                $violations[] = "insights[{$index}].evidence berisi nilai non-integer.";

                return null;
            }

            if (! in_array($item['value'], $allowedNumbers, true)) {
                $violations[] = "insights[{$index}].evidence memuat angka {$item['value']} yang tidak ada di metrics.";

                return null;
            }

            $label = $this->sanitizer->text($item['label'] ?? null, 60);

            if ($label === null) {
                $violations[] = "insights[{$index}].evidence tidak punya label.";

                return null;
            }

            $clean[] = ['label' => $label, 'value' => $item['value']];
        }

        return $clean === [] ? null : $clean;
    }

    /** @return Collection<int, AiInsight> */
    private function persist(User $user, AiRun $run): Collection
    {
        /** @var array<string, mixed> $output */
        $output = $run->structured_output ?? [];
        $validUntil = now()->addMinutes(max(60, AiFeature::FinancialInsight->cacheMinutes()));

        return DB::transaction(function () use ($user, $run, $output, $validUntil): Collection {
            // Retire the previous batch instead of deleting it, so history stays auditable.
            AiInsight::query()
                ->where('user_id', $user->id)
                ->whereNull('dismissed_at')
                ->where('valid_until', '>', now())
                ->update(['valid_until' => now()]);

            foreach ($output['insights'] ?? [] as $position => $insight) {
                AiInsight::query()->create([
                    'user_id' => $user->id,
                    'ai_run_id' => $run->id,
                    'type' => $insight['type'],
                    'severity' => $insight['severity'],
                    'title' => $insight['title'],
                    'summary' => $insight['summary'],
                    'evidence' => $insight['evidence'],
                    'recommended_action' => $insight['recommendedAction'],
                    'action_url' => $insight['actionUrl'],
                    'position' => $position + 1,
                    'valid_until' => $validUntil,
                ]);
            }

            return $this->visible($user);
        });
    }

    /**
     * Flatten every integer in the metrics payload; this is the allowlist the evidence
     * values are checked against.
     *
     * @param  array<string, mixed>  $metrics
     * @return list<int>
     */
    private function numbersIn(array $metrics): array
    {
        $numbers = [];

        array_walk_recursive($metrics, function ($value) use (&$numbers): void {
            if (is_int($value)) {
                $numbers[] = $value;
            }
        });

        return array_values(array_unique($numbers));
    }

    private function receivedBetween(User $user, \DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return (int) InvoicePayment::query()->active()
            ->whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');
    }

    private function oldestOverdueDays(User $user): int
    {
        $oldest = Invoice::query()
            ->where('user_id', $user->id)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', today())
            ->min('due_date');

        return $oldest === null ? 0 : (int) today()->diffInDays($oldest, absolute: true);
    }
}

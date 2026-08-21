<?php

namespace App\Services\Ai\Features;

use App\Enums\Ai\AiFeature;
use App\Enums\Anomaly\AnomalyAction;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiProviderException;
use App\Exceptions\Ai\AiValidationException;
use App\Models\FinancialAnomaly;
use App\Models\User;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\Prompts\AnomalyExplanationPrompt;
use App\Services\Ai\Sanitizer;
use App\Services\Ai\Schemas\AnomalyExplanationSchema;
use App\Services\Ai\Support\AiPrompt;
use App\Services\Subscription\EntitlementService;
use Throwable;

class AnomalyExplanationService
{
    public function __construct(
        private readonly AiOrchestrator $orchestrator,
        private readonly EntitlementService $entitlements,
        private readonly Sanitizer $sanitizer,
    ) {}

    /**
     * Explain one already-detected anomaly. Detection stays entirely deterministic; this
     * only adds the human-readable "why" on top of it.
     *
     * @return array<string, mixed>
     */
    public function explain(User $user, FinancialAnomaly $anomaly, bool $force = false): array
    {
        $this->entitlements->require($user, 'ai.anomaly_explanation');

        // A stored explanation is reused unless the caller explicitly asks for a new one.
        if (! $force && $anomaly->explanation !== null) {
            return $this->present($anomaly, cached: true);
        }

        $payload = $this->context($anomaly);

        try {
            $run = $this->orchestrator->runStructured(
                $user,
                AiFeature::AnomalyExplanation,
                new AiPrompt(
                    systemInstruction: AnomalyExplanationPrompt::system(),
                    userContent: AnomalyExplanationPrompt::user($payload),
                    schema: AnomalyExplanationSchema::definition(),
                    hashPayload: $payload,
                ),
                fn (array $output): array => $this->validate($output, $payload),
            );
        } catch (AiDisabledException|AiProviderException|AiValidationException $exception) {
            return $this->fallback($anomaly, $this->reasonFor($exception));
        }

        /** @var array<string, mixed> $result */
        $result = $run->structured_output ?? [];

        $anomaly->update([
            'ai_run_id' => $run->id,
            'explanation' => $result['explanation'],
            'likely_cause' => $result['likelyCause'],
            'prevention_tip' => $result['preventionTip'],
            'recommended_action' => $result['recommendedAction'],
            'action_url' => $anomaly->deriveActionUrl(),
            'explained_at' => now(),
        ]);

        return $this->present($anomaly->refresh(), cached: false, confidence: (int) $result['confidence'], runId: $run->id);
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validate(array $output, array $payload): array
    {
        $violations = [];

        $action = AnomalyAction::tryFrom((string) ($output['recommendedAction'] ?? ''));
        if ($action === null) {
            $violations[] = 'recommendedAction di luar daftar aksi yang diizinkan.';
        }

        $confidence = $output['confidence'] ?? null;
        if (! is_int($confidence) || $confidence < 0 || $confidence > 100) {
            $violations[] = 'confidence harus bilangan bulat 0-100.';
        }

        $explanation = $this->sanitizer->text($output['explanation'] ?? null, AnomalyExplanationSchema::MAX_EXPLANATION_LENGTH);
        $cause = $this->sanitizer->text($output['likelyCause'] ?? null, AnomalyExplanationSchema::MAX_CAUSE_LENGTH);
        $tip = $this->sanitizer->text($output['preventionTip'] ?? null, AnomalyExplanationSchema::MAX_TIP_LENGTH);

        foreach (['explanation' => $explanation, 'likelyCause' => $cause, 'preventionTip' => $tip] as $field => $value) {
            if ($value === null) {
                $violations[] = "{$field} kosong.";
            }
        }

        // Guard against invented figures: any long number in the prose must exist in the data.
        $allowed = $this->numbersIn($payload);
        foreach ([$explanation, $cause, $tip] as $text) {
            foreach ($this->quotedNumbers((string) $text) as $number) {
                if (! in_array($number, $allowed, true)) {
                    $violations[] = "Angka {$number} tidak ada pada data temuan.";
                }
            }
        }

        if ($violations !== []) {
            throw new AiValidationException($violations);
        }

        return [
            'explanation' => $explanation,
            'likelyCause' => $cause,
            'preventionTip' => $tip,
            'recommendedAction' => $action->value,
            'confidence' => (int) $confidence,
        ];
    }

    /**
     * Pull only currency figures out of the prose. Inventing an AMOUNT is the real risk;
     * years, day counts and percentages are harmless and would otherwise produce false
     * rejections ("Juli 2026" is not an invented number).
     *
     * @return list<int>
     */
    private function quotedNumbers(string $text): array
    {
        preg_match_all('/Rp\s*([\d][\d.,]*)/iu', $text, $matches);

        $numbers = [];

        foreach ($matches[1] ?? [] as $raw) {
            $digits = preg_replace('/\D/', '', $raw) ?? '';

            if ($digits !== '') {
                $numbers[] = (int) $digits;
            }
        }

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function numbersIn(array $payload): array
    {
        $numbers = [];

        array_walk_recursive($payload, function ($value) use (&$numbers): void {
            if (is_int($value)) {
                $numbers[] = $value;
            } elseif (is_string($value)) {
                $digits = preg_replace('/\D/', '', $value) ?? '';
                if ($digits !== '' && strlen($digits) >= 4) {
                    $numbers[] = (int) $digits;
                }
            }
        });

        return array_values(array_unique($numbers));
    }

    /** @return array<string, mixed> */
    private function context(FinancialAnomaly $anomaly): array
    {
        return [
            'type' => $anomaly->type->value,
            'typeLabel' => $anomaly->type->label(),
            'severity' => $anomaly->severity->value,
            'title' => $anomaly->title,
            'detectedFact' => $anomaly->description,
            'amountAtRisk' => (int) $anomaly->amount_at_risk,
            'amountAtRiskFormatted' => 'Rp '.number_format((int) $anomaly->amount_at_risk, 0, ',', '.'),
            'detectedAt' => $anomaly->detected_at?->format('Y-m-d'),
            'details' => $this->sanitizeMetadata($anomaly->metadata ?? []),
            'allowedActions' => AnomalyAction::values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $clean = $this->sanitizer->withoutPii($metadata);

        foreach ($clean as $key => $value) {
            if (is_string($value)) {
                $clean[$key] = $this->sanitizer->text($value, 120);
            }
        }

        return $clean;
    }

    /** @return array<string, mixed> */
    private function present(FinancialAnomaly $anomaly, bool $cached, ?int $confidence = null, ?int $runId = null): array
    {
        return [
            'explanation' => $anomaly->explanation,
            'likelyCause' => $anomaly->likely_cause,
            'preventionTip' => $anomaly->prevention_tip,
            'recommendedAction' => $anomaly->recommended_action?->value,
            'actionLabel' => $anomaly->recommended_action?->label(),
            'actionUrl' => $anomaly->action_url,
            'confidence' => $confidence,
            'aiRunId' => $runId ?? $anomaly->ai_run_id,
            'aiAvailable' => true,
            'source' => $cached ? 'cache' : 'ai',
        ];
    }

    /**
     * Without AI the user still gets the deterministic finding and a safe default action.
     *
     * @return array<string, mixed>
     */
    private function fallback(FinancialAnomaly $anomaly, string $reason): array
    {
        $action = match ($anomaly->source_type) {
            'invoice' => AnomalyAction::ReviewInvoice,
            'bank_transaction' => AnomalyAction::ReviewTransaction,
            default => AnomalyAction::ReviewTransaction,
        };

        return [
            'explanation' => $anomaly->description,
            'likelyCause' => null,
            'preventionTip' => null,
            'recommendedAction' => $action->value,
            'actionLabel' => $action->label(),
            'actionUrl' => $anomaly->deriveActionUrl(),
            'confidence' => null,
            'aiRunId' => null,
            'aiAvailable' => false,
            'source' => 'rules',
            'unavailableReason' => $reason,
        ];
    }

    private function reasonFor(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof AiDisabledException => $exception->reason,
            $exception instanceof AiProviderException => $exception->errorCode,
            default => 'invalid_output',
        };
    }
}

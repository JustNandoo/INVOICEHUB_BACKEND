<?php

namespace App\Services\Ai;

use App\Enums\Ai\AiFeature;
use App\Enums\Ai\AiRunStatus;
use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiProviderException;
use App\Exceptions\Ai\AiQuotaExceededException;
use App\Exceptions\Ai\AiValidationException;
use App\Models\AiRun;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Support\AiPrompt;
use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiOrchestrator
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiCreditService $credits,
        private readonly AiCostEstimator $costs,
    ) {}

    /**
     * Run a feature that expects strict JSON. The optional validator receives the raw
     * decoded payload and must either return the normalised array or throw an
     * AiValidationException; invalid output is retried, never stored as a success.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $validator
     *
     * @throws AiDisabledException|AiProviderException|AiValidationException|AiQuotaExceededException
     */
    public function runStructured(User $user, AiFeature $feature, AiPrompt $prompt, ?callable $validator = null): AiRun
    {
        $this->assertEnabled();
        $this->credits->assertCanSpend($user, $feature);

        $tier = (string) ($feature->config()['model'] ?? 'fast');
        $model = $this->provider->modelFor($tier);
        $hash = $this->inputHash($feature, $model, $prompt);

        if ($cached = $this->cachedRun($user, $feature, $hash)) {
            return $cached;
        }

        $request = new AiRequest(
            model: $model,
            systemInstruction: $prompt->systemInstruction,
            userContent: $prompt->userContent,
            maxOutputTokens: $feature->maxOutputTokens(),
            schema: $prompt->schema,
            thinkingBudget: $feature->thinkingBudget(),
        );

        return $this->execute(
            $user, $feature, $model, $hash,
            fn (): AiResponse => $this->provider->generateStructured($request),
            $validator,
        );
    }

    /**
     * Run a feature that expects free-form text (reminder drafts and similar).
     *
     * @param  (callable(string): array<string, mixed>)|null  $validator
     */
    public function runText(User $user, AiFeature $feature, AiPrompt $prompt, ?callable $validator = null): AiRun
    {
        $this->assertEnabled();
        $this->credits->assertCanSpend($user, $feature);

        $tier = (string) ($feature->config()['model'] ?? 'fast');
        $model = $this->provider->modelFor($tier);
        $hash = $this->inputHash($feature, $model, $prompt);

        if ($cached = $this->cachedRun($user, $feature, $hash)) {
            return $cached;
        }

        $request = new AiRequest(
            model: $model,
            systemInstruction: $prompt->systemInstruction,
            userContent: $prompt->userContent,
            maxOutputTokens: $feature->maxOutputTokens(),
            thinkingBudget: $feature->thinkingBudget(),
        );

        return $this->execute(
            $user, $feature, $model, $hash,
            fn (): AiResponse => $this->provider->generateText($request),
            $validator === null ? null : fn (AiResponse $response): array => $validator($response->text),
            passResponseToValidator: true,
        );
    }

    /**
     * @param  callable(): AiResponse  $call
     */
    private function execute(
        User $user,
        AiFeature $feature,
        string $model,
        string $hash,
        callable $call,
        ?callable $validator,
        bool $passResponseToValidator = false,
    ): AiRun {
        $attempts = max(1, (int) config('ai.max_retries', 2) + 1);
        $inputTokens = 0;
        $outputTokens = 0;
        $lastPayload = null;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $call();
            } catch (AiProviderException $exception) {
                $lastError = $exception;

                if (! $exception->retryable) {
                    break;
                }

                continue;
            }

            $inputTokens += $response->inputTokens;
            $outputTokens += $response->totalOutputTokens();
            $lastPayload = $response->structured;

            try {
                $payload = match (true) {
                    $validator === null => $response->structured ?? ['text' => $response->text],
                    $passResponseToValidator => $validator($response),
                    default => $validator($response->structured ?? []),
                };
            } catch (AiValidationException $exception) {
                $lastError = $exception;

                continue;
            }

            $run = $this->store(
                $user, $feature, $model, $hash, AiRunStatus::Succeeded,
                $payload, $inputTokens, $outputTokens, $response->thinkingTokens, $response->latencyMs,
            );
            $this->spend($user, $feature, $model, $inputTokens, $outputTokens, $run->id);

            return $run;
        }

        $status = $lastError instanceof AiValidationException ? AiRunStatus::InvalidOutput : AiRunStatus::Failed;
        $run = $this->store(
            $user, $feature, $model, $hash, $status,
            $lastPayload, $inputTokens, $outputTokens, 0, 0,
            $lastError instanceof AiProviderException ? $lastError->errorCode : 'invalid_output',
            $lastError?->getMessage(),
        );

        // Tokens were still consumed on failed attempts, so the spend is recorded too.
        if ($inputTokens > 0 || $outputTokens > 0) {
            $this->spend($user, $feature, $model, $inputTokens, $outputTokens, $run->id);
        }

        Log::warning('AI run failed', [
            'aiRunId' => $run->id, 'feature' => $feature->value, 'status' => $status->value,
            'errorCode' => $run->error_code,
        ]);

        throw $lastError ?? new AiProviderException('Panggilan AI gagal tanpa detail.', 'unknown');
    }

    private function assertEnabled(): void
    {
        if (! config('ai.enabled')) {
            throw new AiDisabledException('feature_flag_off');
        }
    }

    private function cachedRun(User $user, AiFeature $feature, string $hash): ?AiRun
    {
        $minutes = $feature->cacheMinutes();

        if ($minutes <= 0) {
            return null;
        }

        return AiRun::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature->value)
            ->where('input_hash', $hash)
            ->where('status', AiRunStatus::Succeeded->value)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->latest('id')
            ->first();
    }

    private function inputHash(AiFeature $feature, string $model, AiPrompt $prompt): string
    {
        try {
            $canonical = json_encode($prompt->hashPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            $canonical = serialize($prompt->hashPayload);
        }

        return hash('sha256', implode('|', [
            $feature->value, $feature->promptVersion(), $model, $canonical,
        ]));
    }

    /** @param array<string, mixed>|null $payload */
    private function store(
        User $user,
        AiFeature $feature,
        string $model,
        string $hash,
        AiRunStatus $status,
        ?array $payload,
        int $inputTokens,
        int $outputTokens,
        int $thinkingTokens,
        int $latencyMs,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): AiRun {
        return AiRun::query()->create([
            'user_id' => $user->id,
            'feature' => $feature->value,
            'provider' => $this->provider->name(),
            'model' => $model,
            'prompt_version' => $feature->promptVersion(),
            'status' => $status->value,
            'input_hash' => $hash,
            'structured_output' => $payload,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'thinking_tokens' => $thinkingTokens,
            'estimated_cost' => $this->costs->estimate($model, $inputTokens, $outputTokens),
            'latency_ms' => $latencyMs,
            'error_code' => $errorCode,
            'error_message' => $errorMessage === null ? null : mb_substr($errorMessage, 0, 1000),
        ]);
    }

    private function spend(User $user, AiFeature $feature, string $model, int $inputTokens, int $outputTokens, int $runId): void
    {
        $this->credits->record(
            $user, $feature, $model, $inputTokens, $outputTokens,
            $this->costs->estimate($model, $inputTokens, $outputTokens), $runId,
        );
    }
}

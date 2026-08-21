<?php

namespace App\Services\Ai\Providers;

use App\Exceptions\Ai\AiProviderException;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class GeminiProvider implements AiProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function modelFor(string $tier): string
    {
        $model = $this->config['models'][$tier] ?? null;

        if (! is_string($model) || $model === '') {
            throw new AiProviderException("Model untuk tier '{$tier}' belum dikonfigurasi.", 'misconfigured');
        }

        return $model;
    }

    public function generateStructured(AiRequest $request): AiResponse
    {
        $response = $this->call($request, structured: true);

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($response->text, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiProviderException(
                'Provider AI mengembalikan JSON yang tidak dapat dibaca: '.$exception->getMessage(),
                'malformed_json',
                retryable: true,
            );
        }

        return new AiResponse(
            $response->model, $response->text, $decoded,
            $response->inputTokens, $response->outputTokens, $response->thinkingTokens,
            $response->latencyMs, $response->finishReason,
        );
    }

    public function generateText(AiRequest $request): AiResponse
    {
        return $this->call($request, structured: false);
    }

    private function call(AiRequest $request, bool $structured): AiResponse
    {
        $key = $this->config['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new AiProviderException('GEMINI_API_KEY belum diisi.', 'misconfigured');
        }

        $url = rtrim((string) $this->config['base_url'], '/')."/models/{$request->model}:generateContent";
        $payload = $this->payload($request, $structured);
        $attempts = max(1, (int) config('ai.max_retries', 2) + 1);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $startedAt = hrtime(true);

            try {
                $response = Http::withHeaders([
                    'x-goog-api-key' => $key,
                    'Content-Type' => 'application/json',
                ])->timeout((int) config('ai.timeout_seconds', 30))->post($url, $payload);
            } catch (ConnectionException $exception) {
                $lastException = AiProviderException::transport($exception->getMessage());

                continue;
            }

            if ($response->failed()) {
                $exception = AiProviderException::fromStatus($response->status(), $response->body());

                if (! $exception->retryable) {
                    throw $exception;
                }

                $lastException = $exception;

                continue;
            }

            return $this->toResponse($request, $response->json() ?? [], (int) ((hrtime(true) - $startedAt) / 1_000_000));
        }

        throw $lastException ?? new AiProviderException('Panggilan ke provider AI gagal.', 'unknown', retryable: true);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function toResponse(AiRequest $request, array $body, int $latencyMs): AiResponse
    {
        $candidate = $body['candidates'][0] ?? null;
        $finishReason = (string) ($candidate['finishReason'] ?? 'UNKNOWN');
        $usage = $body['usageMetadata'] ?? [];

        // Gemini counts thinking tokens against maxOutputTokens, so a blown budget
        // surfaces as MAX_TOKENS with a truncated body. Treat it as retryable.
        if ($finishReason !== 'STOP') {
            throw new AiProviderException(
                "Provider AI berhenti dengan finishReason={$finishReason}.",
                'incomplete_output',
                retryable: $finishReason === 'MAX_TOKENS',
            );
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        if (trim($text) === '') {
            throw new AiProviderException('Provider AI mengembalikan jawaban kosong.', 'empty_output', retryable: true);
        }

        return new AiResponse(
            $request->model,
            $text,
            null,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            (int) ($usage['thoughtsTokenCount'] ?? 0),
            $latencyMs,
            $finishReason,
        );
    }

    /** @return array<string, mixed> */
    private function payload(AiRequest $request, bool $structured): array
    {
        $generationConfig = [
            'temperature' => $request->temperature,
            'maxOutputTokens' => $request->maxOutputTokens,
        ];

        // Gemini 3.x rejects thinkingBudget=0 outright, so a budget of zero means
        // "let the model decide" and the key is omitted entirely.
        if ($request->thinkingBudget > 0) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => $request->thinkingBudget];
        }

        if ($structured && $request->schema !== null) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $this->toGeminiSchema($request->schema);
        }

        return [
            'systemInstruction' => ['parts' => [['text' => $request->systemInstruction]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->userContent]]]],
            'generationConfig' => $generationConfig,
        ];
    }

    /**
     * Translate the provider-neutral schema into Gemini's dialect, which expects
     * upper-case type names.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function toGeminiSchema(array $schema): array
    {
        $translated = [];

        foreach ($schema as $key => $value) {
            $translated[$key] = match (true) {
                $key === 'type' && is_string($value) => strtoupper($value),
                $key === 'properties' && is_array($value) => array_map(
                    fn (array $property): array => $this->toGeminiSchema($property),
                    $value,
                ),
                $key === 'items' && is_array($value) => $this->toGeminiSchema($value),
                default => $value,
            };
        }

        return $translated;
    }
}

<?php

namespace App\Services\Ai\Support;

class AiResponse
{
    /** @param array<string, mixed>|null $structured */
    public function __construct(
        public readonly string $model,
        public readonly string $text,
        public readonly ?array $structured,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $thinkingTokens,
        public readonly int $latencyMs,
        public readonly string $finishReason,
    ) {}

    public function totalOutputTokens(): int
    {
        return $this->outputTokens + $this->thinkingTokens;
    }
}

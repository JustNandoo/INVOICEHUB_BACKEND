<?php

namespace App\Services\Ai\Support;

class AiRequest
{
    /**
     * @param  array<string, mixed>|null  $schema  Provider-neutral JSON schema; the provider translates it.
     */
    public function __construct(
        public readonly string $model,
        public readonly string $systemInstruction,
        public readonly string $userContent,
        public readonly int $maxOutputTokens,
        public readonly ?array $schema = null,
        public readonly int $thinkingBudget = 0,
        public readonly float $temperature = 0.2,
    ) {}
}

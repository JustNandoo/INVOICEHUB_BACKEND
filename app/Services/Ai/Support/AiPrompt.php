<?php

namespace App\Services\Ai\Support;

class AiPrompt
{
    /**
     * @param  array<string, mixed>|null  $schema  Provider-neutral JSON schema.
     * @param  array<string, mixed>  $hashPayload  Canonical data used to build the idempotency hash.
     */
    public function __construct(
        public readonly string $systemInstruction,
        public readonly string $userContent,
        public readonly ?array $schema = null,
        public readonly array $hashPayload = [],
    ) {}
}

<?php

namespace App\Services\Ai\Contracts;

use App\Exceptions\Ai\AiProviderException;
use App\Services\Ai\Support\AiRequest;
use App\Services\Ai\Support\AiResponse;

interface AiProvider
{
    /**
     * Provider identifier stored on every ai_run.
     */
    public function name(): string;

    /**
     * Resolve a routing tier ("reasoning", "fast") into a concrete model name.
     */
    public function modelFor(string $tier): string;

    /**
     * Call the provider expecting a JSON object shaped by $request->schema.
     *
     * @throws AiProviderException
     */
    public function generateStructured(AiRequest $request): AiResponse;

    /**
     * Call the provider expecting free-form text.
     *
     * @throws AiProviderException
     */
    public function generateText(AiRequest $request): AiResponse;
}

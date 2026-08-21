<?php

namespace App\Services\Ai;

class AiCostEstimator
{
    /**
     * Estimate the provider cost of a call in whole rupiah. Pricing is configured in
     * USD per one million tokens; the Gemini free tier is priced at zero.
     */
    public function estimate(string $model, int $inputTokens, int $outputTokens): int
    {
        $pricing = config("ai.pricing.{$model}");

        if (! is_array($pricing)) {
            return 0;
        }

        $usd = ($inputTokens / 1_000_000) * (float) ($pricing['input'] ?? 0)
            + ($outputTokens / 1_000_000) * (float) ($pricing['output'] ?? 0);

        return (int) round($usd * (int) config('ai.usd_to_idr', 16500));
    }
}

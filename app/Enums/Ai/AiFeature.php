<?php

namespace App\Enums\Ai;

enum AiFeature: string
{
    case ReconciliationAnalysis = 'reconciliation_analysis';
    case FinancialInsight = 'financial_insight';
    case PaymentReminderDraft = 'payment_reminder_draft';
    case AnomalyExplanation = 'anomaly_explanation';

    /** @return array<string, mixed> */
    public function config(): array
    {
        return (array) config("ai.features.{$this->value}", []);
    }

    public function credits(): int
    {
        return (int) ($this->config()['credits'] ?? 1);
    }

    public function entitlement(): string
    {
        return (string) ($this->config()['entitlement'] ?? '');
    }

    public function promptVersion(): string
    {
        return (string) ($this->config()['prompt_version'] ?? 'v1');
    }

    public function maxOutputTokens(): int
    {
        return (int) ($this->config()['max_output_tokens'] ?? 1000);
    }

    public function thinkingBudget(): int
    {
        return (int) ($this->config()['thinking_budget'] ?? 0);
    }

    public function cacheMinutes(): int
    {
        return (int) ($this->config()['cache_minutes'] ?? 0);
    }
}

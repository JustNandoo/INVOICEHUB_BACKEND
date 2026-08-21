<?php

namespace App\Models;

use App\Enums\Ai\InsightSeverity;
use App\Enums\Anomaly\AnomalyAction;
use App\Enums\Anomaly\AnomalyStatus;
use App\Enums\Anomaly\AnomalyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAnomaly extends Model
{
    protected $fillable = [
        'user_id', 'type', 'severity', 'status', 'title', 'description', 'amount_at_risk',
        'source_type', 'source_id', 'metadata', 'ai_run_id', 'explanation', 'likely_cause',
        'prevention_tip', 'recommended_action', 'action_url', 'explained_at',
        'detected_at', 'resolved_at', 'resolution', 'notes',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /** @param Builder<FinancialAnomaly> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AnomalyStatus::Open->value);
    }

    /**
     * Where the dashboard should send the user for this finding. Derived from the source
     * record, never from AI output.
     */
    public function deriveActionUrl(): ?string
    {
        return match ($this->source_type) {
            'bank_transaction', 'reconciliation' => '/reconciliation',
            'invoice' => '/invoices/'.$this->source_id,
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'type' => AnomalyType::class,
            'severity' => InsightSeverity::class,
            'status' => AnomalyStatus::class,
            'recommended_action' => AnomalyAction::class,
            'metadata' => 'array',
            'amount_at_risk' => 'integer',
            'source_id' => 'integer',
            'detected_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'explained_at' => 'immutable_datetime',
        ];
    }
}

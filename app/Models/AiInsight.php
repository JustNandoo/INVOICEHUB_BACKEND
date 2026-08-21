<?php

namespace App\Models;

use App\Enums\Ai\InsightAction;
use App\Enums\Ai\InsightSeverity;
use App\Enums\Ai\InsightType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    protected $fillable = [
        'user_id', 'ai_run_id', 'type', 'severity', 'title', 'summary',
        'evidence', 'recommended_action', 'action_url', 'position', 'valid_until', 'dismissed_at',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /** @param Builder<AiInsight> $query */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at')->where('valid_until', '>', now());
    }

    protected function casts(): array
    {
        return [
            'type' => InsightType::class,
            'severity' => InsightSeverity::class,
            'recommended_action' => InsightAction::class,
            'evidence' => 'array',
            'position' => 'integer',
            'valid_until' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
        ];
    }
}

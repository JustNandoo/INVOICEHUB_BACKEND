<?php

namespace App\Models;

use App\Enums\Ai\AiFeature;
use App\Enums\Ai\AiRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends Model
{
    protected $fillable = [
        'user_id', 'feature', 'provider', 'model', 'prompt_version', 'status', 'input_hash',
        'structured_output', 'input_tokens', 'output_tokens', 'thinking_tokens',
        'estimated_cost', 'latency_ms', 'error_code', 'error_message',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AiFeedback::class);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, [AiRunStatus::Succeeded->value, AiRunStatus::Cached->value], true);
    }

    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'status' => AiRunStatus::class,
            'structured_output' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'thinking_tokens' => 'integer',
            'estimated_cost' => 'integer',
            'latency_ms' => 'integer',
        ];
    }
}

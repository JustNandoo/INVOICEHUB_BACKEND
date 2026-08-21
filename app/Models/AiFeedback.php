<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFeedback extends Model
{
    protected $table = 'ai_feedback';

    protected $fillable = [
        'ai_run_id', 'user_id', 'accepted', 'rating', 'corrected_value', 'comment',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'rating' => 'integer',
            'corrected_value' => 'array',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Ai;

use App\Services\Ai\Schemas\PaymentReminderSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReminderDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['nullable', Rule::in(['email', 'whatsapp'])],
            'tone' => ['nullable', Rule::in(PaymentReminderSchema::TONES)],
        ];
    }
}

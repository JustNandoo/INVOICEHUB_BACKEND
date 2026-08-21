<?php

namespace App\Http\Requests\Api\Anomaly;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveAnomalyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['fixed', 'not_an_issue', 'accepted_loss'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

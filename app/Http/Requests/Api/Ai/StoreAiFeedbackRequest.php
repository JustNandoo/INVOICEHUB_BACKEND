<?php

namespace App\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accepted' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'correctedValue' => ['nullable', 'array'],
            'correctedValue.invoiceId' => ['nullable', 'integer'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

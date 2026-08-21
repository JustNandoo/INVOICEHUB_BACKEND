<?php

namespace App\Http\Requests\Api\Anomaly;

use App\Enums\Ai\InsightSeverity;
use App\Enums\Anomaly\AnomalyStatus;
use App\Enums\Anomaly\AnomalyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAnomaliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'perPage' => $this->input('perPage', 15),
            'page' => $this->input('page', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(AnomalyStatus::values())],
            'severity' => ['nullable', Rule::in(InsightSeverity::values())],
            'type' => ['nullable', Rule::in(AnomalyType::values())],
            'perPage' => ['required', 'integer', 'min:1', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }
}

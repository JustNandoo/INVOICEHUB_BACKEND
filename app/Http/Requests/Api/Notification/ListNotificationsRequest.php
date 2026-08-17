<?php

namespace App\Http\Requests\Api\Notification;

use App\Enums\Notification\BusinessNotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['all', 'read', 'unread'])],
            'type' => ['sometimes', Rule::enum(BusinessNotificationType::class)],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'max:255'],
            'newPassword' => ['required', 'string', 'different:currentPassword', Password::defaults()],
            'newPasswordConfirmation' => ['required', 'string', 'same:newPassword'],
        ];
    }
}

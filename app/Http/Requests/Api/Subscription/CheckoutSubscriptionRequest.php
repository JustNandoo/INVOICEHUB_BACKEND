<?php

namespace App\Http\Requests\Api\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutSubscriptionRequest extends FormRequest
{
    /**
     * Klien hanya boleh menyebut paket yang diinginkan. Nominalnya ditentukan
     * server dari katalog, jadi harga tidak bisa dimanipulasi dari browser.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'planCode' => ['required', 'string', Rule::in(array_keys((array) config('subscriptions.plans')))],
        ];
    }
}

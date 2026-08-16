<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->name,
            'email' => $this->email,
            'businessName' => $this->business_name,
            'whatsapp' => $this->whatsapp,
            'city' => $this->city,
            'businessType' => $this->business_type,
            'photoUrl' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'role' => $this->role,
            'emailVerifiedAt' => $this->email_verified_at?->toIso8601String(),
            'passwordChangedAt' => $this->password_changed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

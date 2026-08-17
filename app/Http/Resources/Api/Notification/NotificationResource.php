<?php

namespace App\Http\Resources\Api\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->data['title'],
            'message' => $this->data['message'],
            'tone' => $this->data['tone'],
            'icon' => $this->data['icon'],
            'actionUrl' => $this->data['actionUrl'] ?? null,
            'context' => $this->data['context'] ?? [],
            'isRead' => $this->read_at !== null,
            'readAt' => $this->read_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * @param  array<string, bool|int|float|string|null>  $context
     */
    public function createOnce(
        User $user,
        BusinessNotificationType $type,
        string $dedupeKey,
        string $title,
        string $message,
        NotificationTone $tone,
        NotificationIcon $icon,
        ?string $actionUrl = null,
        array $context = [],
    ): DatabaseNotification {
        return DatabaseNotification::query()->firstOrCreate(
            [
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'dedupe_key' => $dedupeKey,
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => $type->value,
                'data' => [
                    'title' => $title,
                    'message' => $message,
                    'tone' => $tone->value,
                    'icon' => $icon->value,
                    'actionUrl' => $actionUrl,
                    'context' => $context,
                ],
            ],
        );
    }
}

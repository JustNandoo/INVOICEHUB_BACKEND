<?php

namespace App\Enums\Notification;

enum NotificationIcon: string
{
    case Receipt = 'receipt';
    case Alert = 'alert';
    case Celebration = 'celebration';
    case Customer = 'customer';
    case File = 'file';
}

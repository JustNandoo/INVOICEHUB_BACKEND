<?php

return [
    'timezone' => env('NOTIFICATION_TIMEZONE', 'Asia/Jakarta'),
    'invoice_due_days' => (int) env('NOTIFICATION_INVOICE_DUE_DAYS', 2),
    'retention_days' => (int) env('NOTIFICATION_RETENTION_DAYS', 180),
];

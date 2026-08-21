<?php

namespace App\Enums\Notification;

enum BusinessNotificationType: string
{
    case InvoicePaid = 'invoice.paid';
    case InvoiceDueSoon = 'invoice.due_soon';
    case CustomerCreated = 'customer.created';
    case WeeklyReportReady = 'report.weekly_ready';
    case RevenueTargetReached = 'revenue.target_reached';
    case AiInsightsReady = 'ai.insights_ready';
    case AnomalyDetected = 'anomaly.detected';
}

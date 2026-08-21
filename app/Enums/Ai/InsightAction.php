<?php

namespace App\Enums\Ai;

/**
 * The AI picks an action from this closed list; Laravel derives the destination URL.
 * The model never supplies a link, so it cannot point the user anywhere unexpected.
 */
enum InsightAction: string
{
    case SendReminders = 'send_reminders';
    case ReviewReconciliation = 'review_reconciliation';
    case ReviewCustomers = 'review_customers';
    case ReviewTax = 'review_tax';
    case ReviewDrafts = 'review_drafts';
    case NoAction = 'no_action';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function url(): ?string
    {
        return match ($this) {
            self::SendReminders => '/invoices?status=overdue',
            self::ReviewReconciliation => '/reconciliation',
            self::ReviewCustomers => '/customers',
            self::ReviewTax => '/tax-report',
            self::ReviewDrafts => '/invoices?status=draft',
            self::NoAction => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SendReminders => 'Kirim Pengingat',
            self::ReviewReconciliation => 'Periksa Rekonsiliasi',
            self::ReviewCustomers => 'Tinjau Pelanggan',
            self::ReviewTax => 'Periksa Laporan Pajak',
            self::ReviewDrafts => 'Selesaikan Draft Invoice',
            self::NoAction => 'Tidak Ada Tindakan',
        };
    }
}

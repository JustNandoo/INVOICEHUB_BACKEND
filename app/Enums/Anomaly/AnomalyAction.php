<?php

namespace App\Enums\Anomaly;

/**
 * Closed action list for AI explanations. Laravel derives the destination URL from the
 * anomaly source, so the model never supplies a link.
 */
enum AnomalyAction: string
{
    case ReviewTransaction = 'review_transaction';
    case ReconcileManually = 'reconcile_manually';
    case ContactCustomer = 'contact_customer';
    case VerifyFee = 'verify_fee';
    case ReviewInvoice = 'review_invoice';
    case NoAction = 'no_action';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ReviewTransaction => 'Periksa Transaksi',
            self::ReconcileManually => 'Cocokkan Manual',
            self::ContactCustomer => 'Hubungi Pelanggan',
            self::VerifyFee => 'Verifikasi Potongan',
            self::ReviewInvoice => 'Periksa Invoice',
            self::NoAction => 'Tidak Ada Tindakan',
        };
    }
}

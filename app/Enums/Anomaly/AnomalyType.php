<?php

namespace App\Enums\Anomaly;

enum AnomalyType: string
{
    case UnmatchedIncoming = 'unmatched_incoming';
    case ExcessiveFee = 'excessive_fee';
    case ExcessiveDiscount = 'excessive_discount';
    case UnsettledBalance = 'unsettled_balance';
    case DuplicatePayment = 'duplicate_payment';
    case RepeatedReversal = 'repeated_reversal';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::UnmatchedIncoming => 'Uang masuk belum tercocokkan',
            self::ExcessiveFee => 'Potongan tidak wajar',
            self::ExcessiveDiscount => 'Diskon melebihi batas',
            self::UnsettledBalance => 'Sisa tagihan terlupakan',
            self::DuplicatePayment => 'Indikasi pembayaran ganda',
            self::RepeatedReversal => 'Rekonsiliasi dibatalkan berulang',
        };
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return (array) config("anomaly.rules.{$this->value}", []);
    }
}

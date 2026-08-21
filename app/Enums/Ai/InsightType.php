<?php

namespace App\Enums\Ai;

enum InsightType: string
{
    case Cashflow = 'cashflow';
    case Receivable = 'receivable';
    case Reconciliation = 'reconciliation';
    case Customer = 'customer';
    case Tax = 'tax';
    case Growth = 'growth';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

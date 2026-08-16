<?php

namespace App\Services\Invoice;

use Illuminate\Validation\ValidationException;

class InvoiceCalculator
{
    /**
     * @param  array<int, array{description: string, quantity: int, unitPrice: int}>  $items
     * @return array{subtotal: int, taxRateBasisPoints: int, taxAmount: int, discountAmount: int, totalAmount: int, items: array<int, array{description: string, quantity: int, unit_price: int, line_total: int, position: int}>}
     */
    public function calculate(array $items, int $taxRateBasisPoints, int $discountAmount): array
    {
        $normalizedItems = [];
        $subtotal = 0;

        foreach (array_values($items) as $index => $item) {
            $lineTotal = (int) $item['quantity'] * (int) $item['unitPrice'];
            $subtotal += $lineTotal;
            $normalizedItems[] = [
                'description' => trim($item['description']),
                'quantity' => (int) $item['quantity'],
                'unit_price' => (int) $item['unitPrice'],
                'line_total' => $lineTotal,
                'position' => $index + 1,
            ];
        }

        $taxAmount = intdiv(($subtotal * $taxRateBasisPoints) + 5000, 10000);
        $grossTotal = $subtotal + $taxAmount;

        if ($discountAmount > $grossTotal) {
            throw ValidationException::withMessages([
                'discountAmount' => ['Diskon tidak boleh melebihi subtotal ditambah pajak.'],
            ]);
        }

        return [
            'subtotal' => $subtotal,
            'taxRateBasisPoints' => $taxRateBasisPoints,
            'taxAmount' => $taxAmount,
            'discountAmount' => $discountAmount,
            'totalAmount' => $grossTotal - $discountAmount,
            'items' => $normalizedItems,
        ];
    }
}

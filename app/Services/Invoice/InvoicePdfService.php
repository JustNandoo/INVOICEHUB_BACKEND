<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'payments']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->output();
    }
}

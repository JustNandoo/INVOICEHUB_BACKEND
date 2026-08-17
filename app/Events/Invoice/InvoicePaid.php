<?php

namespace App\Events\Invoice;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class InvoicePaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $paymentId,
    ) {}
}

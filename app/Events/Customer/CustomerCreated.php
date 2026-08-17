<?php

namespace App\Events\Customer;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CustomerCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $customerId) {}
}

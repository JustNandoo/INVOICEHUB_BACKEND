<?php

namespace App\Exceptions\Ai;

use RuntimeException;

class AiValidationException extends RuntimeException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('Keluaran AI tidak lolos validasi: '.implode('; ', $violations));
    }
}

<?php

namespace App\Exceptions\Ai;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'provider_error',
        public readonly bool $retryable = false,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }

    public static function transport(string $message): self
    {
        return new self($message, 'transport_error', retryable: true);
    }

    public static function fromStatus(int $status, string $body): self
    {
        // 408/429/5xx are transient; anything else means the request itself is wrong.
        $retryable = $status === 408 || $status === 429 || $status >= 500;

        return new self(
            sprintf('Provider AI membalas HTTP %d: %s', $status, mb_substr($body, 0, 300)),
            match (true) {
                $status === 429 => 'rate_limited',
                $status >= 500 => 'provider_unavailable',
                $status === 401 || $status === 403 => 'unauthorized',
                default => 'bad_request',
            },
            $retryable,
            $status,
        );
    }
}

<?php

namespace App\Services\Hyperliquid;

use RuntimeException;

class HyperliquidRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Hyperliquid REST rate limit budget exhausted, retry after {$retryAfterSeconds}s");
    }
}

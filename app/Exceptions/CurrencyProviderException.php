<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CurrencyProviderException extends RuntimeException
{
    public static function fromApiError(string $provider, string $message): self
    {
        return new self("[{$provider}] {$message}");
    }
}

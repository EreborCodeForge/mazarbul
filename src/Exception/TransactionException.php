<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

use RuntimeException;
use Throwable;

final class TransactionException extends RuntimeException implements MazarbulException
{
    public static function nestedNotSupported(): self
    {
        return new self('Nested transactions are not supported in Mazarbul v1.');
    }

    public static function beginFailed(Throwable $previous): self
    {
        return new self('Failed to begin transaction.', previous: $previous);
    }

    public static function commitFailed(Throwable $previous): self
    {
        return new self('Failed to commit transaction.', previous: $previous);
    }

    public static function rollbackFailed(Throwable $previous): self
    {
        return new self('Failed to roll back transaction.', previous: $previous);
    }
}

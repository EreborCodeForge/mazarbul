<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

use RuntimeException;
use Throwable;

final class BulkException extends RuntimeException implements MazarbulException
{
    public static function invalidChunkSize(int $chunkSize): self
    {
        return new self(sprintf('Bulk chunk size must be >= 1, got %d.', $chunkSize));
    }

    public static function invalidColumns(): self
    {
        return new self('Bulk operation requires at least one column.');
    }

    public static function operationFailed(string $operation, Throwable $previous): self
    {
        return new self(
            sprintf('Bulk %s operation failed.', $operation),
            previous: $previous,
        );
    }

    public static function retriesExhausted(string $operation, int $attempts, Throwable $previous): self
    {
        return new self(
            sprintf('Bulk %s failed after %d attempt(s).', $operation, $attempts),
            previous: $previous,
        );
    }
}

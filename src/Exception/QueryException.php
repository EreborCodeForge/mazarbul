<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

use RuntimeException;
use Throwable;

final class QueryException extends RuntimeException implements MazarbulException
{
    public static function executionFailed(string $sql, Throwable $previous): self
    {
        return new self(
            'Query execution failed.',
            previous: $previous,
        );
    }
}

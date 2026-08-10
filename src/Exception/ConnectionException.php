<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

use RuntimeException;
use Throwable;

final class ConnectionException extends RuntimeException implements MazarbulException
{
    public static function unknown(string $name): self
    {
        return new self(sprintf('Unknown connection "%s".', $name));
    }

    public static function duplicate(string $name): self
    {
        return new self(sprintf('Connection "%s" is already defined.', $name));
    }

    public static function openFailed(string $name, Throwable $previous): self
    {
        return new self(
            sprintf('Failed to open connection "%s".', $name),
            previous: $previous,
        );
    }

    public static function notConnected(string $name): self
    {
        return new self(sprintf('Connection "%s" is not connected.', $name));
    }
}

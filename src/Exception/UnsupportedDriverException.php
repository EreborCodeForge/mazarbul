<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

use RuntimeException;

final class UnsupportedDriverException extends RuntimeException implements MazarbulException
{
    public static function forDriver(string $driver): self
    {
        return new self(sprintf(
            'Unsupported database driver "%s". Supported drivers: mysql, pgsql.',
            $driver,
        ));
    }

    public static function forDsn(string $dsn): self
    {
        return new self(sprintf(
            'Unable to resolve a supported driver from DSN "%s".',
            $dsn,
        ));
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\PostgreSql;

use EreborCodeForge\Mazarbul\Driver\DriverCapabilities;

final class PostgreSqlCapabilities
{
    public static function create(): DriverCapabilities
    {
        return new DriverCapabilities(
            supportsUnbufferedReads: false,
            supportsServerSideCursor: true,
            supportsReturning: true,
            maxBindParameters: 65535,
        );
    }
}

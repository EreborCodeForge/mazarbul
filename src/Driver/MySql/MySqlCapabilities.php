<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\MySql;

use EreborCodeForge\Mazarbul\Driver\DriverCapabilities;

final class MySqlCapabilities
{
    public static function create(): DriverCapabilities
    {
        return new DriverCapabilities(
            supportsUnbufferedReads: true,
            supportsServerSideCursor: false,
            supportsReturning: false,
            maxBindParameters: 65535,
        );
    }
}

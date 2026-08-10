<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\PostgreSql;

use EreborCodeForge\Mazarbul\Driver\Support\AbstractDialect;

final class PostgreSqlDialect extends AbstractDialect
{
    #[\Override]
    protected function quoteChar(): string
    {
        return '"';
    }
}

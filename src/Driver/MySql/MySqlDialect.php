<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\MySql;

use EreborCodeForge\Mazarbul\Driver\Support\AbstractDialect;

final class MySqlDialect extends AbstractDialect
{
    #[\Override]
    protected function quoteChar(): string
    {
        return '`';
    }
}

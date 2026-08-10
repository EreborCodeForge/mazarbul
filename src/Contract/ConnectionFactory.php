<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use PDO;

interface ConnectionFactory
{
    public function create(ConnectionConfig $config): PDO;
}

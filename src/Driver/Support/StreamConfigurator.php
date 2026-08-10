<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\Support;

use PDO;

interface StreamConfigurator
{
    public function configure(PDO $pdo): void;

    public function restore(PDO $pdo): void;
}

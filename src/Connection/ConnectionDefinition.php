<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

final readonly class ConnectionDefinition
{
    public function __construct(
        public string $name,
        public ConnectionConfig $config,
        public string $driver,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver;

use EreborCodeForge\Mazarbul\Bulk\Strategy\BulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Contract\Dialect;
use EreborCodeForge\Mazarbul\Driver\Support\StreamConfigurator;
use PDO;

final readonly class Driver
{
    public function __construct(
        public string $name,
        public Dialect $dialect,
        public DriverCapabilities $capabilities,
        public StreamConfigurator $streamConfigurator,
        public BulkUpdateStrategy $bulkUpdateStrategy,
    ) {
    }

    public function configureStreaming(PDO $pdo): void
    {
        $this->streamConfigurator->configure($pdo);
    }

    public function restoreStreaming(PDO $pdo): void
    {
        $this->streamConfigurator->restore($pdo);
    }
}

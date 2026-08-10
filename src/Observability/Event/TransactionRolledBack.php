<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class TransactionRolledBack
{
    public function __construct(
        public string $connectionName,
        public string $reason,
    ) {
    }
}

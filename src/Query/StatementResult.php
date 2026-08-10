<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Query;

final readonly class StatementResult
{
    public function __construct(
        public int $rowCount,
    ) {
    }
}

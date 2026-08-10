<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

interface Dialect
{
    public function quoteIdentifier(string $identifier): string;

    /**
     * @throws \InvalidArgumentException
     */
    public function assertValidIdentifier(string $identifier): void;
}

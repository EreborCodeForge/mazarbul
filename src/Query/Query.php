<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Query;

final readonly class Query
{
    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function __construct(
        public string $sql,
        public array $params = [],
    ) {
    }
}

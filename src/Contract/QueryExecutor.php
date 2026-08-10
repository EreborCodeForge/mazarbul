<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

use EreborCodeForge\Mazarbul\Query\QueryResult;
use EreborCodeForge\Mazarbul\Query\StatementResult;

interface QueryExecutor
{
    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): StatementResult;

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array;

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed;

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function query(string $sql, array $params = []): QueryResult;
}

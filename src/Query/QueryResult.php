<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Query;

use PDOStatement;

final class QueryResult
{
    public function __construct(
        private readonly PDOStatement $statement,
    ) {
    }

    public function statement(): PDOStatement
    {
        return $this->statement;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
        $row = $this->statement->fetch();
        if ($row === false) {
            return null;
        }

        /** @var array<string, mixed> $row */
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->statement->fetchAll();

        return $rows;
    }

    public function close(): void
    {
        $this->statement->closeCursor();
    }
}

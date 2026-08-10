<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

use PDO;

interface Connection
{
    public function name(): string;

    public function driver(): string;

    public function pdo(): PDO;

    public function isConnected(): bool;

    public function isInTransaction(): bool;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function lastInsertId(?string $name = null): string|false;

    public function ping(): bool;
}

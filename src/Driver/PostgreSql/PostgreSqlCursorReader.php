<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\PostgreSql;

use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use PDO;
use Throwable;

/**
 * Streams rows via DECLARE CURSOR / FETCH to avoid client-side full buffering.
 *
 * @internal
 */
final class PostgreSqlCursorReader
{
    private string $cursorName;

    private bool $ownsTransaction = false;

    private bool $declared = false;

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function __construct(
        private readonly ManagedConnection $connection,
        private readonly string $sql,
        private readonly array $params,
    ) {
        $this->cursorName = 'mazarbul_c_' . str_replace('.', '_', uniqid('', true));
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function rows(): \Generator
    {
        $pdo = $this->connection->pdo();
        $failed = true;

        if (!$this->connection->isInTransaction()) {
            $this->connection->beginTransaction();
            $this->ownsTransaction = true;
        }

        try {
            $declare = sprintf(
                'DECLARE %s NO SCROLL CURSOR FOR %s',
                $this->quoteCursorName(),
                $this->sql,
            );
            $statement = $pdo->prepare($declare);
            $statement->execute($this->params);
            $statement->closeCursor();
            $this->declared = true;

            $fetchSql = sprintf('FETCH FORWARD 1 FROM %s', $this->quoteCursorName());
            while (true) {
                $fetch = $pdo->query($fetchSql);
                if ($fetch === false) {
                    break;
                }
                $row = $fetch->fetch(PDO::FETCH_ASSOC);
                $fetch->closeCursor();
                if ($row === false) {
                    break;
                }
                /** @var array<string, mixed> $row */
                yield $row;
            }
            $failed = false;
        } finally {
            $this->cleanup($pdo, $failed);
        }
    }

    private function cleanup(PDO $pdo, bool $failed): void
    {
        if ($this->declared) {
            try {
                $pdo->exec(sprintf('CLOSE %s', $this->quoteCursorName()));
            } catch (Throwable) {
                // best effort
            }
            $this->declared = false;
        }

        if (!$this->ownsTransaction) {
            return;
        }

        try {
            if (!$this->connection->isInTransaction()) {
                $this->ownsTransaction = false;

                return;
            }
            if ($failed) {
                $this->connection->rollBack();
            } else {
                $this->connection->commit();
            }
        } catch (Throwable) {
            try {
                if ($this->connection->isInTransaction()) {
                    $this->connection->rollBack();
                }
            } catch (Throwable) {
            }
        }

        $this->ownsTransaction = false;
    }

    private function quoteCursorName(): string
    {
        return '"' . str_replace('"', '""', $this->cursorName) . '"';
    }
}

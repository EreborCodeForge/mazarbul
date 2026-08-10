<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\MySql;

use EreborCodeForge\Mazarbul\Driver\Support\StreamConfigurator;
use PDO;

/**
 * Enables MySQL unbuffered queries for true row streaming when using mysqlnd.
 */
final class MySqlStreamConfigurator implements StreamConfigurator
{
    private mixed $previousBuffered = null;

    public function configure(PDO $pdo): void
    {
        if (!$this->isMysql($pdo) || !defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            return;
        }

        /** @var mixed $current */
        $current = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $this->previousBuffered = $current;
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    }

    public function restore(PDO $pdo): void
    {
        if (
            !$this->isMysql($pdo)
            || !defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')
            || $this->previousBuffered === null
        ) {
            return;
        }

        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $this->previousBuffered);
        $this->previousBuffered = null;
    }

    private function isMysql(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}

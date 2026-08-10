<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlBulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlCapabilities;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlDialect;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlStreamConfigurator;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlBulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlCapabilities;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlDialect;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlStreamConfigurator;
use EreborCodeForge\Mazarbul\Exception\UnsupportedDriverException;

final class DriverResolver
{
    public function resolveFromConfig(ConnectionConfig $config): Driver
    {
        return $this->resolveFromDsn($config->dsn);
    }

    public function resolveFromDsn(string $dsn): Driver
    {
        $driver = $this->extractDriver($dsn);

        return match ($driver) {
            'mysql' => $this->mysql(),
            'pgsql', 'postgres', 'postgresql' => $this->pgsql(),
            default => throw UnsupportedDriverException::forDriver($driver),
        };
    }

    public function resolve(string $driver): Driver
    {
        return match (strtolower($driver)) {
            'mysql' => $this->mysql(),
            'pgsql', 'postgres', 'postgresql' => $this->pgsql(),
            default => throw UnsupportedDriverException::forDriver($driver),
        };
    }

    private function mysql(): Driver
    {
        return new Driver(
            name: 'mysql',
            dialect: new MySqlDialect(),
            capabilities: MySqlCapabilities::create(),
            streamConfigurator: new MySqlStreamConfigurator(),
            bulkUpdateStrategy: new MySqlBulkUpdateStrategy(),
        );
    }

    private function pgsql(): Driver
    {
        return new Driver(
            name: 'pgsql',
            dialect: new PostgreSqlDialect(),
            capabilities: PostgreSqlCapabilities::create(),
            streamConfigurator: new PostgreSqlStreamConfigurator(),
            bulkUpdateStrategy: new PostgreSqlBulkUpdateStrategy(),
        );
    }

    private function extractDriver(string $dsn): string
    {
        if ($dsn === '' || !str_contains($dsn, ':')) {
            throw UnsupportedDriverException::forDsn($dsn);
        }

        $driver = strtolower(strtok($dsn, ':') ?: '');
        if ($driver === '') {
            throw UnsupportedDriverException::forDsn($dsn);
        }

        return $driver;
    }
}

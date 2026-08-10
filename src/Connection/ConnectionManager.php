<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

use EreborCodeForge\Mazarbul\Contract\ConnectionFactory;
use EreborCodeForge\Mazarbul\Contract\ConnectionLifecycle;
use EreborCodeForge\Mazarbul\Contract\Observer;
use EreborCodeForge\Mazarbul\Driver\DriverResolver;
use EreborCodeForge\Mazarbul\Exception\ConnectionException;
use EreborCodeForge\Mazarbul\Observability\NullObserver;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Support\Clock;
use EreborCodeForge\Mazarbul\Support\SystemClock;

final class ConnectionManager implements ConnectionLifecycle
{
    /** @var array<string, ManagedConnection> */
    private array $connections = [];

    public function __construct(
        private readonly ConnectionFactory $factory,
        private readonly DriverResolver $driverResolver = new DriverResolver(),
        private readonly Observer $observer = new NullObserver(),
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function define(string $name, ConnectionConfig $config): void
    {
        if (isset($this->connections[$name])) {
            throw ConnectionException::duplicate($name);
        }

        $driver = $this->driverResolver->resolveFromConfig($config);
        $definition = new ConnectionDefinition($name, $config, $driver->name);

        $this->connections[$name] = new ManagedConnection(
            definition: $definition,
            factory: $this->factory,
            driver: $driver,
            observer: $this->observer,
            clock: $this->clock,
        );
    }

    public function has(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    public function connection(string $name): ManagedConnection
    {
        return $this->connections[$name] ?? throw ConnectionException::unknown($name);
    }

    public function database(string $name): Database
    {
        return new Database($this->connection($name), $this->observer);
    }

    public function onWorkerStart(): void
    {
        // reserved for future warm-up hooks
    }

    public function onRequestStart(): void
    {
        // reserved for request-scoped markers
    }

    public function onRequestEnd(): void
    {
        foreach ($this->connections as $connection) {
            $connection->onRequestEnd();
        }
    }

    public function onWorkerStop(): void
    {
        foreach ($this->connections as $connection) {
            $connection->onWorkerStop();
        }
    }
}

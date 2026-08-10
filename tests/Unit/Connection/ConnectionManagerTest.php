<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Connection;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\ConnectionState;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Exception\ConnectionException;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionOpened;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionReconnected;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionRolledBack;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionManager::class)]
#[Group('unit')]
final class ConnectionManagerTest extends TestCase
{
    private CountingConnectionFactory $factory;
    private FakeClock $clock;
    private SpyObserver $observer;
    private ConnectionManager $manager;

    protected function setUp(): void
    {
        $this->factory = new CountingConnectionFactory();
        $this->clock = new FakeClock(1_000.0);
        $this->observer = new SpyObserver();
        $this->manager = new ConnectionManager(
            factory: $this->factory,
            observer: $this->observer,
            clock: $this->clock,
        );
    }

    public function testDefineDoesNotOpenConnection(): void
    {
        $this->manager->define('default', $this->mysqlConfig());

        self::assertSame(0, $this->factory->createCount);
        self::assertTrue($this->manager->has('default'));
        self::assertFalse($this->manager->connection('default')->isConnected());
        self::assertSame(ConnectionState::NotConnected, $this->manager->connection('default')->state());
        self::assertSame(0, $this->observer->countOf(ConnectionOpened::class));
    }

    public function testFirstIoOpensExactlyOnceAndSubsequentReuses(): void
    {
        $this->manager->define('default', $this->mysqlConfig());
        $connection = $this->manager->connection('default');

        $pdo1 = $connection->pdo();
        self::assertSame(1, $this->factory->createCount);
        self::assertSame(1, $this->observer->countOf(ConnectionOpened::class));
        self::assertTrue($connection->isConnected());
        self::assertSame(ConnectionState::Connected, $connection->state());

        $pdo2 = $connection->pdo();
        $pdo3 = $connection->pdo();

        self::assertSame(1, $this->factory->createCount);
        self::assertSame($pdo1, $pdo2);
        self::assertSame($pdo1, $pdo3);
        self::assertSame(1, $this->observer->countOf(ConnectionOpened::class));
    }

    public function testUnknownConnectionThrows(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unknown connection "missing"');
        $this->manager->connection('missing');
    }

    public function testDuplicateDefinitionThrows(): void
    {
        $this->manager->define('default', $this->mysqlConfig());

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('already defined');
        $this->manager->define('default', $this->mysqlConfig());
    }

    public function testIdleTimeoutClosesAndReopensOnNextIo(): void
    {
        $this->manager->define('default', $this->mysqlConfig(new LifecyclePolicy(
            idleTimeoutSeconds: 30,
            maxLifetimeSeconds: null,
            healthCheckIntervalSeconds: null,
        )));

        $connection = $this->manager->connection('default');
        $connection->pdo();
        self::assertSame(1, $this->factory->createCount);
        $generation = $connection->generation();

        $this->clock->advance(30.0);
        $connection->pdo();

        self::assertSame(2, $this->factory->createCount);
        self::assertSame($generation + 1, $connection->generation());
        self::assertSame(1, $this->observer->countOf(ConnectionReconnected::class));
        self::assertTrue($connection->isConnected());
    }

    public function testMaxLifetimeRotatesConnection(): void
    {
        $this->manager->define('default', $this->mysqlConfig(new LifecyclePolicy(
            idleTimeoutSeconds: null,
            maxLifetimeSeconds: 60,
            healthCheckIntervalSeconds: null,
        )));

        $connection = $this->manager->connection('default');
        $connection->pdo();
        self::assertSame(1, $this->factory->createCount);
        $generation = $connection->generation();

        $this->clock->advance(60.0);
        $connection->pdo();

        self::assertSame(2, $this->factory->createCount);
        self::assertSame($generation + 1, $connection->generation());
        self::assertSame(1, $this->observer->countOf(ConnectionReconnected::class));
    }

    public function testOnRequestEndRollsBackLeakedTransaction(): void
    {
        $this->manager->define('default', $this->mysqlConfig(new LifecyclePolicy(
            idleTimeoutSeconds: null,
            maxLifetimeSeconds: null,
            healthCheckIntervalSeconds: null,
        )));

        $connection = $this->manager->connection('default');
        $connection->beginTransaction();
        self::assertTrue($connection->isInTransaction());

        $this->manager->onRequestEnd();

        self::assertFalse($connection->isInTransaction());
        $rolledBack = $this->observer->ofType(TransactionRolledBack::class);
        self::assertCount(1, $rolledBack);
        $event = $rolledBack[0];
        self::assertInstanceOf(TransactionRolledBack::class, $event);
        self::assertSame('request_end', $event->reason);
    }

    public function testDatabaseHelperReturnsQueryableFacade(): void
    {
        $this->manager->define('default', $this->mysqlConfig());
        $database = $this->manager->database('default');

        self::assertSame(0, $this->factory->createCount);
        self::assertSame(1, $database->scalar('SELECT 1'));
        self::assertSame(1, $this->factory->createCount);
    }

    private function mysqlConfig(?LifecyclePolicy $lifecycle = null): ConnectionConfig
    {
        return new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            lifecycle: $lifecycle ?? new LifecyclePolicy(
                idleTimeoutSeconds: null,
                maxLifetimeSeconds: null,
                healthCheckIntervalSeconds: null,
            ),
        );
    }
}

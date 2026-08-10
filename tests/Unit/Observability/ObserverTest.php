<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Observability;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Observability\CompositeObserver;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionOpened;
use EreborCodeForge\Mazarbul\Observability\Event\QueryExecuted;
use EreborCodeForge\Mazarbul\Observability\NullObserver;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullObserver::class)]
#[CoversClass(CompositeObserver::class)]
#[CoversClass(SpyObserver::class)]
#[Group('unit')]
final class ObserverTest extends TestCase
{
    public function testNullObserverIsNoOp(): void
    {
        $observer = new NullObserver();

        $observer->notify(new ConnectionOpened('default', 'mysql', 1));
        $observer->notify(new QueryExecuted('default', 'mysql', 'execute', 0.01, 1));

        $this->addToAssertionCount(1);
    }

    public function testCompositeObserverForwardsToAllObservers(): void
    {
        $first = new SpyObserver();
        $second = new SpyObserver();
        $composite = new CompositeObserver([$first, $second]);

        $event = new ConnectionOpened('default', 'mysql', 1);
        $composite->notify($event);

        self::assertSame([$event], $first->events);
        self::assertSame([$event], $second->events);
        self::assertSame(1, $first->countOf(ConnectionOpened::class));
        self::assertSame(1, $second->countOf(ConnectionOpened::class));
    }

    public function testSpyObserverCollectsConnectionOpenedOnFirstIo(): void
    {
        $spy = new SpyObserver();
        $factory = new CountingConnectionFactory();
        $manager = new ConnectionManager(
            factory: $factory,
            observer: $spy,
            clock: new FakeClock(),
        );
        $manager->define('default', new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            lifecycle: new LifecyclePolicy(
                idleTimeoutSeconds: null,
                maxLifetimeSeconds: null,
                healthCheckIntervalSeconds: null,
            ),
        ));

        self::assertSame(0, $spy->countOf(ConnectionOpened::class));

        $manager->connection('default')->pdo();

        self::assertSame(1, $spy->countOf(ConnectionOpened::class));
        $opened = $spy->ofType(ConnectionOpened::class);
        $event = $opened[0];
        self::assertInstanceOf(ConnectionOpened::class, $event);
        self::assertSame('default', $event->connectionName);
        self::assertSame('mysql', $event->driver);
        self::assertSame(1, $event->generation);
        self::assertSame(1, $factory->createCount);
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Stream;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Exception\StreamConsumedException;
use EreborCodeForge\Mazarbul\Stream\DatabaseResultStream;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DatabaseResultStream::class)]
final class DatabaseResultStreamTest extends TestCase
{
    public function testOrderPreservedAndSinglePass(): void
    {
        $db = $this->database();
        $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        $db->execute("INSERT INTO items (id, name) VALUES (1, 'a'), (2, 'b'), (3, 'c')");

        $stream = $db->stream('SELECT id, name FROM items ORDER BY id');
        $names = [];
        foreach ($stream as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['a', 'b', 'c'], $names);
        self::assertTrue($stream->isConsumed());

        $this->expectException(StreamConsumedException::class);
        foreach ($stream as $row) {
            unset($row);
        }
    }

    public function testClosesOnEarlyBreak(): void
    {
        $db = $this->database();
        $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $db->execute('INSERT INTO items (id) VALUES (1), (2), (3)');

        $stream = $db->stream('SELECT id FROM items ORDER BY id');
        foreach ($stream as $row) {
            if ((int) $row['id'] === 1) {
                break;
            }
        }

        self::assertTrue($stream->isConsumed());
        self::assertFalse($db->connection()->hasActiveStreams());
    }

    public function testClosesAfterExceptionDuringIteration(): void
    {
        $db = $this->database();
        $db->execute('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $db->execute('INSERT INTO items (id) VALUES (1), (2)');

        $stream = $db->stream('SELECT id FROM items ORDER BY id');
        try {
            foreach ($stream as $row) {
                throw new RuntimeException('boom');
            }
            self::fail('Expected RuntimeException');
        } catch (RuntimeException) {
            self::assertTrue($stream->isConsumed());
            self::assertFalse($db->connection()->hasActiveStreams());
        }
    }

    private function database(): \EreborCodeForge\Mazarbul\Query\Database
    {
        $manager = new ConnectionManager(
            factory: new CountingConnectionFactory(),
            observer: new SpyObserver(),
            clock: new FakeClock(),
        );
        $manager->define('default', new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            username: 'test',
            password: 'test',
            lifecycle: new LifecyclePolicy(
                idleTimeoutSeconds: null,
                maxLifetimeSeconds: null,
                healthCheckIntervalSeconds: null,
            ),
        ));

        return $manager->database('default');
    }
}

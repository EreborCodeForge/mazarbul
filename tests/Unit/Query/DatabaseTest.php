<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Query;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Database::class)]
#[Group('unit')]
final class DatabaseTest extends TestCase
{
    private CountingConnectionFactory $factory;
    private Database $database;

    protected function setUp(): void
    {
        $this->factory = new CountingConnectionFactory();
        $observer = new SpyObserver();
        $clock = new FakeClock();
        $manager = new ConnectionManager(
            factory: $this->factory,
            observer: $observer,
            clock: $clock,
        );
        $manager->define('default', new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=test',
            lifecycle: new LifecyclePolicy(
                idleTimeoutSeconds: null,
                maxLifetimeSeconds: null,
                healthCheckIntervalSeconds: null,
            ),
        ));

        $this->database = new Database(
            connection: $manager->connection('default'),
            observer: $observer,
            clock: $clock,
        );

        self::assertSame(0, $this->factory->createCount);

        $this->database->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT)',
        );
        self::assertSame(1, $this->factory->createCount);
    }

    public function testExecuteInsertsRows(): void
    {
        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Ada', 'ada@example.test'],
        );
        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Grace', 'grace@example.test'],
        );

        self::assertSame(2, $this->database->scalar('SELECT COUNT(*) FROM users'));
        self::assertSame(1, $this->factory->createCount);
    }

    public function testFetchOneReturnsAssociativeRowOrNull(): void
    {
        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Ada', 'ada@example.test'],
        );

        $row = $this->database->fetchOne('SELECT id, name, email FROM users WHERE name = ?', ['Ada']);
        self::assertNotNull($row);
        self::assertSame('Ada', $row['name']);
        self::assertSame('ada@example.test', $row['email']);

        self::assertNull($this->database->fetchOne('SELECT id FROM users WHERE name = ?', ['Missing']));
    }

    public function testFetchAllReturnsAllRows(): void
    {
        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?), (?, ?)',
            ['Ada', 'ada@example.test', 'Grace', 'grace@example.test'],
        );

        $rows = $this->database->fetchAll('SELECT name FROM users ORDER BY id');
        self::assertSame(['Ada', 'Grace'], array_column($rows, 'name'));
    }

    public function testScalarReturnsFirstColumnValue(): void
    {
        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Ada', 'ada@example.test'],
        );

        self::assertSame('Ada', $this->database->scalar('SELECT name FROM users WHERE id = 1'));
        self::assertNull($this->database->scalar('SELECT name FROM users WHERE id = 999'));
    }

    public function testLastInsertId(): void
    {
        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Ada', 'ada@example.test'],
        );

        $id = $this->database->lastInsertId();
        self::assertNotFalse($id);
        self::assertSame('1', (string) $id);

        $this->database->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Grace', 'grace@example.test'],
        );
        self::assertSame('2', (string) $this->database->lastInsertId());
    }
}

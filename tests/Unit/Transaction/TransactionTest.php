<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Transaction;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Exception\TransactionException;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionCommitted;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionRolledBack;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionStarted;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use EreborCodeForge\Mazarbul\Transaction\TransactionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TransactionManager::class)]
#[Group('unit')]
final class TransactionTest extends TestCase
{
    private Database $database;
    private SpyObserver $observer;

    protected function setUp(): void
    {
        $factory = new CountingConnectionFactory();
        $this->observer = new SpyObserver();
        $clock = new FakeClock();
        $manager = new ConnectionManager(
            factory: $factory,
            observer: $this->observer,
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
            observer: $this->observer,
            clock: $clock,
        );
        $this->database->execute('CREATE TABLE accounts (id INTEGER PRIMARY KEY, balance INTEGER NOT NULL)');
        $this->database->execute('INSERT INTO accounts (id, balance) VALUES (1, 100)');
    }

    public function testCommitOnSuccessAndReturnsCallbackValue(): void
    {
        $result = $this->database->transaction(function (Database $db): string {
            $db->execute('UPDATE accounts SET balance = balance + 50 WHERE id = 1');

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(150, $this->database->scalar('SELECT balance FROM accounts WHERE id = 1'));
        self::assertSame(1, $this->observer->countOf(TransactionStarted::class));
        self::assertSame(1, $this->observer->countOf(TransactionCommitted::class));
        self::assertSame(0, $this->observer->countOf(TransactionRolledBack::class));
        self::assertFalse($this->database->connection()->isInTransaction());
    }

    public function testRollbackOnException(): void
    {
        try {
            (void) $this->database->transaction(function (Database $db): void {
                $db->execute('UPDATE accounts SET balance = balance - 40 WHERE id = 1');
                throw new RuntimeException('fail');
            });
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            self::assertSame('fail', $e->getMessage());
        }

        self::assertSame(100, $this->database->scalar('SELECT balance FROM accounts WHERE id = 1'));
        self::assertSame(1, $this->observer->countOf(TransactionStarted::class));
        self::assertSame(1, $this->observer->countOf(TransactionRolledBack::class));
        self::assertSame(0, $this->observer->countOf(TransactionCommitted::class));
        self::assertFalse($this->database->connection()->isInTransaction());
    }

    public function testNestedTransactionThrowsTransactionException(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('Nested transactions are not supported');

        (void) $this->database->transaction(function (Database $db): void {
            (void) $db->transaction(static function (): void {
                // nested
            });
        });
    }

    public function testExplicitBeginTwiceThrowsNestedTransactionException(): void
    {
        $connection = $this->database->connection();
        $connection->beginTransaction();

        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('Nested transactions are not supported');
        $connection->beginTransaction();
    }
}

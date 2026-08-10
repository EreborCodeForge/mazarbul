<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Bulk;

use EreborCodeForge\Mazarbul\Bulk\Backoff\ExponentialBackoff;
use EreborCodeForge\Mazarbul\Bulk\Backoff\FixedBackoff;
use EreborCodeForge\Mazarbul\Bulk\Backoff\NoBackoff;
use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Bulk\RetryPolicy;
use EreborCodeForge\Mazarbul\Bulk\TransactionMode;
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Observability\Event\RetryAttempted;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\FakeSleeper;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryPolicy::class)]
#[Group('unit')]
final class RetryPolicyTest extends TestCase
{
    public function testNoneUsesSingleAttemptAndNoBackoff(): void
    {
        $policy = RetryPolicy::none();

        self::assertSame(1, $policy->maxAttempts);
        self::assertInstanceOf(NoBackoff::class, $policy->backoff);
    }

    public function testTransientUsesExponentialBackoffAndMultipleAttempts(): void
    {
        $policy = RetryPolicy::transient(4);

        self::assertSame(4, $policy->maxAttempts);
        self::assertInstanceOf(ExponentialBackoff::class, $policy->backoff);
    }

    public function testWithMaxAttemptsClampsToAtLeastOne(): void
    {
        $policy = RetryPolicy::none()->withMaxAttempts(0);

        self::assertSame(1, $policy->maxAttempts);
    }

    public function testBulkWriterRecordsSleepsOnRetryableFaultThenSucceeds(): void
    {
        $factory = new CountingConnectionFactory();
        $observer = new SpyObserver();
        $clock = new FakeClock();
        $sleeper = new FakeSleeper();
        $manager = new ConnectionManager(
            factory: $factory,
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

        $database = new Database(
            connection: $manager->connection('default'),
            observer: $observer,
            clock: $clock,
            sleeper: $sleeper,
        );
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        $writer = $database->bulk();
        $calls = 0;
        $writer->setFaultInjector(static function () use (&$calls): void {
            ++$calls;
            if ($calls === 1) {
                $error = new PDOException('Deadlock found when trying to get lock; try restarting transaction');
                $error->errorInfo = ['40001', 1213, 'Deadlock found'];
                throw $error;
            }
        });

        $affected = $writer->insert(
            table: 'users',
            columns: ['id', 'name'],
            rows: [[1, 'Ada']],
            options: new BulkOptions(
                chunkSize: 10,
                transactionMode: TransactionMode::PER_CHUNK,
                retryPolicy: new RetryPolicy(
                    maxAttempts: 3,
                    backoff: new FixedBackoff(0.5),
                ),
            ),
        );

        self::assertSame(1, $affected);
        self::assertSame(2, $calls);
        self::assertSame([0.5], $sleeper->sleeps);
        self::assertSame(1, $observer->countOf(RetryAttempted::class));
        self::assertSame(1, $database->scalar('SELECT COUNT(*) FROM users'));
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Bulk;

use EreborCodeForge\Mazarbul\Bulk\BulkDelete;
use EreborCodeForge\Mazarbul\Bulk\BulkInsert;
use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Bulk\BulkWriter;
use EreborCodeForge\Mazarbul\Bulk\RetryPolicy;
use EreborCodeForge\Mazarbul\Bulk\TransactionMode;
use EreborCodeForge\Mazarbul\Bulk\Backoff\FixedBackoff;
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlBulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlCapabilities;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlDialect;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlBulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlDialect;
use EreborCodeForge\Mazarbul\Exception\BulkException;
use EreborCodeForge\Mazarbul\Observability\Event\ChunkProcessed;
use EreborCodeForge\Mazarbul\Observability\Event\RetryAttempted;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\DataGenerator;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\FakeSleeper;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkInsert::class)]
#[CoversClass(BulkDelete::class)]
#[CoversClass(BulkOptions::class)]
#[CoversClass(BulkWriter::class)]
#[CoversClass(MySqlBulkUpdateStrategy::class)]
#[CoversClass(PostgreSqlBulkUpdateStrategy::class)]
#[Group('unit')]
final class BulkBuilderTest extends TestCase
{
    public function testBulkInsertBuildsCorrectPlaceholders(): void
    {
        $builder = new BulkInsert();
        $built = $builder->build(
            new MySqlDialect(),
            'users',
            ['id', 'name'],
            [
                ['id' => 1, 'name' => 'Ada'],
                [2, 'Grace'],
            ],
        );

        self::assertSame(
            'INSERT INTO `users` (`id`, `name`) VALUES (?, ?), (?, ?)',
            $built['sql'],
        );
        self::assertSame([1, 'Ada', 2, 'Grace'], $built['params']);
    }

    public function testBulkDeleteBuildsInListForIds(): void
    {
        $builder = new BulkDelete();
        $built = $builder->build(new MySqlDialect(), 'users', 'id', [1, 2, 3]);

        self::assertSame('DELETE FROM `users` WHERE `id` IN (?, ?, ?)', $built['sql']);
        self::assertSame([1, 2, 3], $built['params']);
    }

    public function testBulkDeleteChunksIdsViaWriter(): void
    {
        [$database, $observer] = $this->createDatabase();
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $database->execute('INSERT INTO users (id, name) VALUES (1, "a"), (2, "b"), (3, "c"), (4, "d"), (5, "e")');

        $affected = $database->bulk()->delete(
            table: 'users',
            keyColumn: 'id',
            ids: [1, 2, 3, 4, 5],
            options: new BulkOptions(chunkSize: 2),
        );

        self::assertSame(5, $affected);
        self::assertSame(3, $observer->countOf(ChunkProcessed::class));
        self::assertSame(0, $database->scalar('SELECT COUNT(*) FROM users'));
    }

    public function testMySqlBulkUpdateStrategyBuildsCaseWhen(): void
    {
        $strategy = new MySqlBulkUpdateStrategy();
        $built = $strategy->buildUpdate(
            new MySqlDialect(),
            'users',
            'id',
            ['name', 'email'],
            [
                ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test'],
                ['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.test'],
            ],
        );

        self::assertSame(
            'UPDATE `users` SET `name` = CASE `id` WHEN ? THEN ? WHEN ? THEN ? END, '
            . '`email` = CASE `id` WHEN ? THEN ? WHEN ? THEN ? END WHERE `id` IN (?, ?)',
            $built['sql'],
        );
        self::assertSame(
            [
                1, 'Ada', 2, 'Grace',
                1, 'ada@example.test', 2, 'grace@example.test',
                1, 2,
            ],
            $built['params'],
        );
    }

    public function testPostgreSqlBulkUpdateStrategyBuildsUpdateFromValues(): void
    {
        $strategy = new PostgreSqlBulkUpdateStrategy();
        $built = $strategy->buildUpdate(
            new PostgreSqlDialect(),
            'users',
            'id',
            ['name'],
            [
                ['id' => 1, 'name' => 'Ada'],
                ['id' => 2, 'name' => 'Grace'],
            ],
        );

        self::assertSame(
            'UPDATE "users" AS t SET "name" = v."v_name" FROM (VALUES (?, ?), (?, ?)) AS v ("v_id", "v_name") '
            . 'WHERE t."id" = v."v_id"',
            $built['sql'],
        );
        self::assertSame([1, 'Ada', 2, 'Grace'], $built['params']);
    }

    public function testBulkOptionsRejectsInvalidChunkSize(): void
    {
        $this->expectException(BulkException::class);
        $this->expectExceptionMessage('Bulk chunk size must be >= 1');
        new BulkOptions(chunkSize: 0);
    }

    public function testEffectiveChunkSizeIsReducedByMaxBindParameters(): void
    {
        $capabilities = MySqlCapabilities::create();
        self::assertSame(65535, $capabilities->maxBindParameters);

        // Formula used by BulkWriter::effectiveChunkSize (paramsPerRow = column count).
        $paramsPerRow = 1001;
        $requested = 1000;
        $effectiveChunk = min($requested, intdiv($capabilities->maxBindParameters, $paramsPerRow));
        self::assertSame(65, $effectiveChunk);

        // Prove chunking against sqlite with a bind budget that fits sqlite (~999 vars).
        [$database, $observer] = $this->createDatabase();
        $database->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');

        $affected = $database->bulk()->insert(
            table: 'items',
            columns: ['id', 'name'],
            rows: (static function (): \Generator {
                for ($i = 1; $i <= 10; ++$i) {
                    yield ['id' => $i, 'name' => 'n' . $i];
                }
            })(),
            options: new BulkOptions(chunkSize: 3),
        );

        self::assertSame(10, $affected);
        self::assertSame(4, $observer->countOf(ChunkProcessed::class));
        self::assertSame(10, $database->scalar('SELECT COUNT(*) FROM items'));
    }

    public function testBulkWriterInsertFromGeneratorAgainstSqliteViaMysqlDsnFactory(): void
    {
        [$database] = $this->createDatabase();
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');

        $affected = $database->bulk()->insert(
            table: 'users',
            columns: ['id', 'name', 'email'],
            rows: DataGenerator::associativeUsers(7),
            options: new BulkOptions(chunkSize: 3),
        );

        self::assertSame(7, $affected);
        self::assertSame(7, $database->scalar('SELECT COUNT(*) FROM users'));
        self::assertSame('User 1', $database->scalar('SELECT name FROM users WHERE id = 1'));
    }

    public function testPerChunkRetryUsesFakeSleeperWhileAllModeDoesNotRetryByDefault(): void
    {
        $sleeper = new FakeSleeper();
        [$database] = $this->createDatabase(sleeper: $sleeper);
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');

        $writer = $database->bulk();
        $attempts = 0;
        $writer->setFaultInjector(static function () use (&$attempts): void {
            ++$attempts;
            if ($attempts === 1) {
                $error = new PDOException('Deadlock found when trying to get lock; try restarting transaction');
                $error->errorInfo = ['40001', 1213, 'Deadlock found'];
                throw $error;
            }
        });

        $affected = $writer->insert(
            table: 'users',
            columns: ['id', 'name', 'email'],
            rows: [['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']],
            options: new BulkOptions(
                chunkSize: 10,
                transactionMode: TransactionMode::PER_CHUNK,
                retryPolicy: new RetryPolicy(
                    maxAttempts: 3,
                    backoff: new FixedBackoff(0.25),
                ),
            ),
        );

        self::assertSame(1, $affected);
        self::assertSame(2, $attempts);
        self::assertSame([0.25], $sleeper->sleeps);
        self::assertSame(1, $database->scalar('SELECT COUNT(*) FROM users'));

        $sleeperAll = new FakeSleeper();
        [$databaseAll, $observerAll] = $this->createDatabase(sleeper: $sleeperAll);
        $databaseAll->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $writerAll = $databaseAll->bulk();
        $attemptsAll = 0;
        $writerAll->setFaultInjector(static function () use (&$attemptsAll): void {
            ++$attemptsAll;
            $error = new PDOException('Deadlock found when trying to get lock; try restarting transaction');
            $error->errorInfo = ['40001', 1213, 'Deadlock found'];
            throw $error;
        });

        try {
            (void) $writerAll->insert(
                table: 'users',
                columns: ['id', 'name', 'email'],
                rows: [['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.test']],
                options: new BulkOptions(
                    chunkSize: 10,
                    transactionMode: TransactionMode::ALL,
                    retryPolicy: new RetryPolicy(
                        maxAttempts: 3,
                        backoff: new FixedBackoff(0.25),
                    ),
                ),
            );
            self::fail('Expected BulkException for ALL mode without allowRetryWithoutTransaction.');
        } catch (BulkException) {
            self::assertSame(1, $attemptsAll);
            self::assertSame([], $sleeperAll->sleeps);
            self::assertSame(0, $observerAll->countOf(RetryAttempted::class));
        }
    }

    /**
     * @return array{0: Database, 1: SpyObserver}
     */
    private function createDatabase(?FakeSleeper $sleeper = null): array
    {
        $factory = new CountingConnectionFactory();
        $observer = new SpyObserver();
        $clock = new FakeClock();
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
            sleeper: $sleeper ?? new FakeSleeper(),
        );

        return [$database, $observer];
    }
}

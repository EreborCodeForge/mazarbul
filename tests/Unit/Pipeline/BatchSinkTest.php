<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Pipeline;

use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Bulk\TransactionMode;
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Pipeline\Pipeline;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Tests\Support\CountingConnectionFactory;
use EreborCodeForge\Mazarbul\Tests\Support\DataGenerator;
use EreborCodeForge\Mazarbul\Tests\Support\FakeClock;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pipeline::class)]
final class BatchSinkTest extends TestCase
{
    public function testToBatchInsertFromPipelineSourceToTarget(): void
    {
        $source = $this->database('source');
        $target = $this->database('target');

        $source->execute('CREATE TABLE legacy_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $target->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');

        foreach (DataGenerator::associativeUsers(5) as $row) {
            $source->execute(
                'INSERT INTO legacy_users (id, name, email) VALUES (?, ?, ?)',
                [$row['id'], $row['name'], $row['email']],
            );
        }

        $count = $source
            ->stream('SELECT id, name, email FROM legacy_users ORDER BY id')
            ->pipeline()
            ->map(static fn(array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'email' => strtolower((string) $row['email']),
            ])
            ->toBatchInsert(
                database: $target,
                table: 'users',
                columns: ['id', 'name', 'email'],
                chunkSize: 2,
            );

        self::assertSame(5, $count);
        self::assertSame(5, $target->scalar('SELECT COUNT(*) FROM users'));
        self::assertSame('user1@example.test', $target->scalar('SELECT email FROM users WHERE id = 1'));
    }

    public function testToBatchUpdateUpdatesTargetRows(): void
    {
        $database = $this->database('default');
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
        $database->execute("INSERT INTO users (id, name, status) VALUES (1, 'Ada', 'pending'), (2, 'Grace', 'pending')");

        $updated = Pipeline::from([
            ['id' => 1, 'status' => 'active'],
            ['id' => 2, 'status' => 'active'],
        ])->toBatchUpdate(
            database: $database,
            table: 'users',
            keyColumn: 'id',
            updateColumns: ['status'],
            chunkSize: 10,
            options: new BulkOptions(
                chunkSize: 10,
                transactionMode: TransactionMode::PER_CHUNK,
            ),
        );

        self::assertGreaterThanOrEqual(2, $updated);
        self::assertSame('active', $database->scalar('SELECT status FROM users WHERE id = 1'));
        self::assertSame('active', $database->scalar('SELECT status FROM users WHERE id = 2'));
    }

    private function database(string $name): Database
    {
        $manager = new ConnectionManager(
            factory: new CountingConnectionFactory(),
            observer: new SpyObserver(),
            clock: new FakeClock(),
        );
        $manager->define($name, new ConnectionConfig(
            dsn: 'mysql:host=localhost;dbname=' . $name,
            username: 'test',
            password: 'test',
            lifecycle: new LifecyclePolicy(
                idleTimeoutSeconds: null,
                maxLifetimeSeconds: null,
                healthCheckIntervalSeconds: null,
            ),
        ));

        return $manager->database($name);
    }
}

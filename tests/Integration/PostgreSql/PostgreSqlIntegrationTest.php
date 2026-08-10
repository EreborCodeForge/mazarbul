<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Integration\PostgreSql;

use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Bulk\TransactionMode;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionOpened;
use EreborCodeForge\Mazarbul\Tests\Integration\IntegrationTestCase;
use EreborCodeForge\Mazarbul\Tests\Support\DataGenerator;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class PostgreSqlIntegrationTest extends IntegrationTestCase
{
    protected function dsn(): string
    {
        return getenv('DB_PGSQL_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=mazarbul';
    }

    protected function username(): string
    {
        return getenv('DB_PGSQL_USER') ?: 'mazarbul';
    }

    protected function password(): string
    {
        return getenv('DB_PGSQL_PASSWORD') ?: 'mazarbul';
    }

    protected function driver(): string
    {
        return 'pgsql';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute('DROP TABLE IF EXISTS mazarbul_users');
        $this->db->execute(
            'CREATE TABLE mazarbul_users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'pending\'
            )',
        );
    }

    public function testConnectionOpenAndReuse(): void
    {
        self::assertSame(1, $this->observer->countOf(ConnectionOpened::class));
        $this->db->scalar('SELECT 1');
        self::assertSame(1, $this->observer->countOf(ConnectionOpened::class));
        self::assertSame('pgsql', $this->db->connection()->driver());
    }

    public function testTraditionalApiAndTransaction(): void
    {
        $id = $this->db->transaction(function ($db): int {
            $db->execute(
                'INSERT INTO mazarbul_users (name, email, status) VALUES (?, ?, ?)',
                ['Ada', 'ada@example.test', 'active'],
            );

            return (int) $db->lastInsertId('mazarbul_users_id_seq');
        });

        $row = $this->db->fetchOne('SELECT * FROM mazarbul_users WHERE id = ?', [$id]);
        self::assertNotNull($row);
        self::assertSame('Ada', $row['name']);
    }

    public function testStreaming(): void
    {
        for ($i = 1; $i <= 4; ++$i) {
            $this->db->execute(
                'INSERT INTO mazarbul_users (name, email) VALUES (?, ?)',
                ['User' . $i, 'u' . $i . '@example.test'],
            );
        }

        $ids = [];
        foreach ($this->db->stream('SELECT id FROM mazarbul_users ORDER BY id') as $row) {
            self::assertIsArray($row);
            self::assertIsNumeric($row['id']);
            $ids[] = (int) $row['id'];
        }

        self::assertCount(4, $ids);
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    public function testBulkInsertUpdateDelete(): void
    {
        $inserted = $this->db->bulk()->insert(
            table: 'mazarbul_users',
            columns: ['name', 'email', 'status'],
            rows: (static function (): \Generator {
                foreach (DataGenerator::associativeUsers(5) as $user) {
                    yield [
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'status' => 'pending',
                    ];
                }
            })(),
            options: new BulkOptions(chunkSize: 2, transactionMode: TransactionMode::PER_CHUNK),
        );

        self::assertSame(5, $inserted);

        $ids = [];
        foreach ($this->db->fetchAll('SELECT id FROM mazarbul_users ORDER BY id') as $row) {
            self::assertIsNumeric($row['id']);
            $ids[] = (int) $row['id'];
        }

        $updates = [];
        foreach ($ids as $id) {
            $updates[] = ['id' => $id, 'status' => 'active'];
        }

        (void) $this->db->bulk()->update(
            table: 'mazarbul_users',
            keyColumn: 'id',
            updateColumns: ['status'],
            rows: $updates,
            options: new BulkOptions(chunkSize: 2),
        );

        self::assertSame(5, $this->db->scalar("SELECT COUNT(*) FROM mazarbul_users WHERE status = 'active'"));

        $deleted = $this->db->bulk()->delete(
            table: 'mazarbul_users',
            keyColumn: 'id',
            ids: $ids,
            options: new BulkOptions(chunkSize: 2),
        );

        self::assertSame(5, $deleted);
        self::assertSame(0, $this->db->scalar('SELECT COUNT(*) FROM mazarbul_users'));
    }

    public function testIdentifierQuotingUsesDoubleQuotes(): void
    {
        $quoted = $this->db->connection()->driverObject()->dialect->quoteIdentifier('mazarbul_users');
        self::assertSame('"mazarbul_users"', $quoted);
    }
}

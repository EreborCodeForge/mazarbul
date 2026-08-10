<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Driver;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Driver\DriverResolver;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlDialect;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlDialect;
use EreborCodeForge\Mazarbul\Exception\UnsupportedDriverException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySqlDialect::class)]
#[CoversClass(PostgreSqlDialect::class)]
#[CoversClass(DriverResolver::class)]
#[Group('unit')]
final class DialectTest extends TestCase
{
    public function testMySqlQuotesWithBackticks(): void
    {
        $dialect = new MySqlDialect();

        self::assertSame('`users`', $dialect->quoteIdentifier('users'));
        self::assertSame('`order_items`', $dialect->quoteIdentifier('order_items'));
    }

    public function testPostgreSqlQuotesWithDoubleQuotes(): void
    {
        $dialect = new PostgreSqlDialect();

        self::assertSame('"users"', $dialect->quoteIdentifier('users'));
        self::assertSame('"order_items"', $dialect->quoteIdentifier('order_items'));
    }

    #[DataProvider('invalidIdentifiers')]
    public function testInvalidIdentifierIsRejected(string $identifier): void
    {
        $dialect = new MySqlDialect();

        $this->expectException(InvalidArgumentException::class);
        $dialect->quoteIdentifier($identifier);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'starts with digit' => ['1users'];
        yield 'hyphen' => ['user-name'];
        yield 'space' => ['user name'];
        yield 'semicolon injection' => ['users;drop'];
        yield 'dot path' => ['public.users'];
    }

    public function testAssertValidIdentifierThrowsForInvalid(): void
    {
        $dialect = new PostgreSqlDialect();

        $this->expectException(InvalidArgumentException::class);
        $dialect->assertValidIdentifier('bad-id');
    }

    public function testResolveMysqlFromName(): void
    {
        $resolver = new DriverResolver();
        $driver = $resolver->resolve('mysql');

        self::assertSame('mysql', $driver->name);
        self::assertInstanceOf(MySqlDialect::class, $driver->dialect);
    }

    public function testResolvePgsqlAliases(): void
    {
        $resolver = new DriverResolver();

        self::assertSame('pgsql', $resolver->resolve('pgsql')->name);
        self::assertSame('pgsql', $resolver->resolve('postgres')->name);
        self::assertSame('pgsql', $resolver->resolve('postgresql')->name);
        self::assertInstanceOf(PostgreSqlDialect::class, $resolver->resolve('pgsql')->dialect);
    }

    public function testResolveFromMysqlDsn(): void
    {
        $resolver = new DriverResolver();
        $driver = $resolver->resolveFromDsn('mysql:host=localhost;dbname=test');

        self::assertSame('mysql', $driver->name);
    }

    public function testResolveFromPgsqlDsn(): void
    {
        $resolver = new DriverResolver();
        $driver = $resolver->resolveFromConfig(new ConnectionConfig(
            dsn: 'pgsql:host=localhost;dbname=test',
        ));

        self::assertSame('pgsql', $driver->name);
    }

    public function testUnsupportedDriverThrows(): void
    {
        $resolver = new DriverResolver();

        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('Unsupported database driver "oracle"');
        $resolver->resolve('oracle');
    }

    public function testSqliteDsnDoesNotSilentlyFallBackToMysql(): void
    {
        $resolver = new DriverResolver();

        try {
            $driver = $resolver->resolveFromDsn('sqlite::memory:');
            self::fail('Expected UnsupportedDriverException, got driver "' . $driver->name . '".');
        } catch (UnsupportedDriverException $e) {
            self::assertStringContainsString('sqlite', strtolower($e->getMessage()));
            self::assertStringContainsString('unsupported', strtolower($e->getMessage()));
        }
    }

    public function testEmptyDsnThrowsUnsupportedDriverException(): void
    {
        $resolver = new DriverResolver();

        $this->expectException(UnsupportedDriverException::class);
        $resolver->resolveFromDsn('');
    }
}

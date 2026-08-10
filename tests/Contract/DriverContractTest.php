<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Contract;

use EreborCodeForge\Mazarbul\Contract\Dialect;
use EreborCodeForge\Mazarbul\Driver\MySql\MySqlDialect;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlDialect;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Shared dialect contract assertions for MySQL and PostgreSQL.
 */
#[CoversClass(MySqlDialect::class)]
#[CoversClass(PostgreSqlDialect::class)]
#[Group('contract')]
final class DriverContractTest extends TestCase
{
    /**
     * @return iterable<string, array{0: Dialect, 1: string}>
     */
    public static function dialects(): iterable
    {
        yield 'mysql' => [new MySqlDialect(), '`'];
        yield 'pgsql' => [new PostgreSqlDialect(), '"'];
    }

    #[DataProvider('dialects')]
    public function testQuoteIdentifierWrapsWithDriverQuoteChar(Dialect $dialect, string $quote): void
    {
        self::assertSame($quote . 'users' . $quote, $dialect->quoteIdentifier('users'));
        self::assertSame($quote . 'order_items' . $quote, $dialect->quoteIdentifier('order_items'));
        self::assertSame($quote . '_private' . $quote, $dialect->quoteIdentifier('_private'));
    }

    #[DataProvider('dialects')]
    public function testAssertValidIdentifierAcceptsSafeNames(Dialect $dialect, string $quote): void
    {
        $dialect->assertValidIdentifier('users');
        $dialect->assertValidIdentifier('User_1');
        $dialect->assertValidIdentifier('_tmp');

        self::assertSame($quote . 'User_1' . $quote, $dialect->quoteIdentifier('User_1'));
    }

    #[DataProvider('dialects')]
    public function testAssertValidIdentifierRejectsUnsafeNames(Dialect $dialect, string $quote): void
    {
        unset($quote);

        $this->expectException(InvalidArgumentException::class);
        $dialect->assertValidIdentifier('users;drop');
    }

    #[DataProvider('dialects')]
    public function testQuoteIdentifierRejectsEmpty(Dialect $dialect, string $quote): void
    {
        unset($quote);

        $this->expectException(InvalidArgumentException::class);
        $dialect->quoteIdentifier('');
    }

    #[DataProvider('dialects')]
    public function testQuoteIdentifierRejectsDottedPaths(Dialect $dialect, string $quote): void
    {
        unset($quote);

        $this->expectException(InvalidArgumentException::class);
        $dialect->quoteIdentifier('schema.table');
    }
}

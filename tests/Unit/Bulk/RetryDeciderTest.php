<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Bulk;

use EreborCodeForge\Mazarbul\Bulk\RetryDecider;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RetryDecider::class)]
#[Group('unit')]
final class RetryDeciderTest extends TestCase
{
    public function testRetryableDeadlockPdoExceptionBySqlState(): void
    {
        $decider = new RetryDecider();
        $error = new PDOException('SQLSTATE[40001]: Serialization failure');
        $error->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];

        self::assertTrue($decider->isRetryable($error));
    }

    public function testRetryableDeadlockPdoExceptionByMessage(): void
    {
        $decider = new RetryDecider();
        $error = new PDOException('Deadlock found when trying to get lock; try restarting transaction');
        $error->errorInfo = ['HY000', 1213, 'Deadlock found when trying to get lock'];

        self::assertTrue($decider->isRetryable($error));
    }

    public function testRetryablePostgresDeadlockSqlState(): void
    {
        $decider = new RetryDecider();
        $error = new PDOException('deadlock detected');
        $error->errorInfo = ['40P01', 0, 'deadlock detected'];

        self::assertTrue($decider->isRetryable($error));
    }

    public function testNonRetryableSyntaxErrorIsNotRetried(): void
    {
        $decider = new RetryDecider();
        $error = new PDOException('SQLSTATE[42000]: Syntax error or access violation');
        $error->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];

        self::assertFalse($decider->isRetryable($error));
    }

    public function testNonPdoExceptionIsNotRetryable(): void
    {
        $decider = new RetryDecider();

        self::assertFalse($decider->isRetryable(new RuntimeException('boom')));
    }

    public function testRetryableWhenWrappedAsPrevious(): void
    {
        $decider = new RetryDecider();
        $inner = new PDOException('server has gone away');
        $inner->errorInfo = ['HY000', 2006, 'server has gone away'];
        $outer = new RuntimeException('wrapper', previous: $inner);

        self::assertTrue($decider->isRetryable($outer));
    }

    #[DataProvider('retryableSqlStates')]
    public function testRetryableSqlStates(string $sqlState): void
    {
        $decider = new RetryDecider();
        $error = new PDOException('transient');
        $error->errorInfo = [$sqlState, 0, 'transient'];

        self::assertTrue($decider->isRetryable($error));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function retryableSqlStates(): iterable
    {
        yield 'serialization' => ['40001'];
        yield 'pgsql deadlock' => ['40P01'];
        yield 'connection exception' => ['08000'];
        yield 'connection failure' => ['08S01'];
    }
}

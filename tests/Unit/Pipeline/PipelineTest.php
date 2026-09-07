<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Pipeline;

use EreborCodeForge\Mazarbul\Contract\PipelineStage;
use EreborCodeForge\Mazarbul\Pipeline\Pipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pipeline::class)]
#[Group('unit')]
final class PipelineTest extends TestCase
{
    public function testMapAndFilterAreLazyUntilCollect(): void
    {
        $touched = false;
        $source = (static function () use (&$touched) {
            $touched = true;
            yield 1;
            yield 2;
            yield 3;
        })();

        $pipeline = Pipeline::from($source)
            ->map(static fn(int $n): int => $n * 2)
            ->filter(static fn(int $n): bool => $n > 2);

        self::assertFalse($touched);
        self::assertSame([4, 6], $pipeline->collect());
        self::assertTrue($touched);
    }

    public function testMapAndFilterAreLazyUntilEach(): void
    {
        $touched = false;
        $source = (static function () use (&$touched) {
            $touched = true;
            yield 'a';
            yield 'b';
        })();

        $pipeline = Pipeline::from($source)->map(static fn(string $s): string => strtoupper($s));
        self::assertFalse($touched);

        $seen = [];
        $pipeline->each(static function (string $value) use (&$seen): void {
            $seen[] = $value;
        });

        self::assertTrue($touched);
        self::assertSame(['A', 'B'], $seen);
    }

    public function testMapAndFilterCompose(): void
    {
        $result = Pipeline::from([1, 2, 3, 4, 5])
            ->map(static fn(int $n): int => $n + 1)
            ->filter(static fn(int $n): bool => $n % 2 === 0)
            ->map(static fn(int $n): int => $n * 10)
            ->collect();

        self::assertSame([20, 40, 60], $result);
    }

    public function testFlatMapFlattensMappedIterables(): void
    {
        $result = Pipeline::from([1, 2, 3])
            ->flatMap(static fn(int $n): array => [$n, $n * 10])
            ->collect();

        self::assertSame([1, 10, 2, 20, 3, 30], $result);
    }

    public function testTapDoesNotAlterValues(): void
    {
        $tapped = [];
        $result = Pipeline::from(['x', 'y'])
            ->tap(static function (string $value) use (&$tapped): void {
                $tapped[] = $value;
            })
            ->map(static fn(string $value): string => strtoupper($value))
            ->collect();

        self::assertSame(['x', 'y'], $tapped);
        self::assertSame(['X', 'Y'], $result);
    }

    public function testTakeStopsEarlyAndDoesNotPullRemainingSource(): void
    {
        $yielded = 0;
        $source = (static function () use (&$yielded) {
            for ($i = 0; $i < 100; ++$i) {
                ++$yielded;
                yield $i;
            }
        })();

        $result = Pipeline::from($source)->take(3)->collect();

        self::assertSame([0, 1, 2], $result);
        self::assertSame(3, $yielded);
    }

    public function testTakeBeforeFilterLimitsSourceThenFilters(): void
    {
        $result = Pipeline::from([1, 2, 3, 4, 5])
            ->take(3)
            ->filter(static fn(int $n): bool => $n % 2 === 0)
            ->collect();

        self::assertSame([2], $result);
    }

    public function testFilterBeforeTakeLimitsFilteredOutput(): void
    {
        $result = Pipeline::from([1, 2, 3, 4, 5])
            ->filter(static fn(int $n): bool => $n % 2 === 0)
            ->take(1)
            ->collect();

        self::assertSame([2], $result);
    }

    public function testChunkEmitsCompleteAndPartialChunks(): void
    {
        $result = Pipeline::from([1, 2, 3, 4, 5])->chunk(2)->collect();

        self::assertSame([[1, 2], [3, 4], [5]], $result);
    }

    public function testChunkWithExactMultipleHasNoPartialTail(): void
    {
        $result = Pipeline::from([1, 2, 3, 4])->chunk(2)->collect();

        self::assertSame([[1, 2], [3, 4]], $result);
    }

    public function testThroughCustomStage(): void
    {
        $stage = new class implements PipelineStage {
            public function apply(iterable $input): iterable
            {
                foreach ($input as $item) {
                    yield (string) $item . '!';
                }
            }
        };

        $result = Pipeline::from([1, 2])->through($stage)->collect();

        self::assertSame(['1!', '2!'], $result);
    }

    public function testFirstReturnsFirstItemOrNull(): void
    {
        self::assertSame(10, Pipeline::from([10, 20, 30])->first());
        self::assertNull(Pipeline::from([])->first());
    }

    public function testReduceAggregatesValues(): void
    {
        $sum = Pipeline::from([1, 2, 3, 4])->reduce(
            static fn(int $carry, int $item): int => $carry + $item,
            0,
        );

        self::assertSame(10, $sum);
    }

    public function testCollectWithLimit(): void
    {
        self::assertSame([1, 2], Pipeline::from([1, 2, 3, 4])->collect(2));
    }

    public function testOrderIsPreservedThroughStages(): void
    {
        $result = Pipeline::from(['c', 'a', 'b'])
            ->filter(static fn(string $s): bool => $s !== 'a')
            ->map(static fn(string $s): string => strtoupper($s))
            ->collect();

        self::assertSame(['C', 'B'], $result);
    }
}

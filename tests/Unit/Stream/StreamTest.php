<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Stream;

use EreborCodeForge\Mazarbul\Exception\StreamConsumedException;
use EreborCodeForge\Mazarbul\Stream\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stream::class)]
#[Group('unit')]
final class StreamTest extends TestCase
{
    public function testSourceClosureIsNotCalledUntilIteration(): void
    {
        $called = false;
        $stream = new Stream(static function () use (&$called) {
            $called = true;
            yield 'a';
            yield 'b';
        });

        self::assertFalse($called);
        self::assertFalse($stream->isConsumed());

        $values = iterator_to_array($stream, false);

        self::assertTrue($called);
        self::assertSame(['a', 'b'], $values);
    }

    public function testOrderIsPreserved(): void
    {
        $stream = new Stream(static function () {
            yield 10;
            yield 20;
            yield 30;
        });

        self::assertSame([10, 20, 30], iterator_to_array($stream, false));
    }

    public function testRecreatableStreamCanIterateTwice(): void
    {
        $invocations = 0;
        $stream = new Stream(static function () use (&$invocations) {
            ++$invocations;
            yield 'x';
            yield 'y';
        }, recreatable: true);

        self::assertSame(['x', 'y'], iterator_to_array($stream, false));
        self::assertFalse($stream->isConsumed());
        self::assertSame(['x', 'y'], iterator_to_array($stream, false));
        self::assertSame(2, $invocations);
    }

    public function testNonRecreatableStreamThrowsOnSecondIteration(): void
    {
        $stream = new Stream(static function () {
            yield 1;
            yield 2;
        }, recreatable: false);

        self::assertSame([1, 2], iterator_to_array($stream, false));
        self::assertTrue($stream->isConsumed());

        $this->expectException(StreamConsumedException::class);
        iterator_to_array($stream, false);
    }

    public function testClosedStreamThrowsOnIteration(): void
    {
        $stream = new Stream(static function () {
            yield 1;
        });
        $stream->close();

        self::assertTrue($stream->isConsumed());
        $this->expectException(StreamConsumedException::class);
        iterator_to_array($stream, false);
    }

    public function testPipelineFactoryReturnsPipelineBoundToStream(): void
    {
        $stream = new Stream(static function () {
            yield 1;
            yield 2;
            yield 3;
        });

        self::assertSame([2, 4, 6], $stream->pipeline()->map(static fn(int $n): int => $n * 2)->collect());
    }

    /**
     * DatabaseResultStream is single-pass (recreatable=false conceptually).
     * Stream with recreatable=false models the same consumption contract.
     */
    public function testSinglePassSemanticsMatchDatabaseResultStreamContract(): void
    {
        $stream = new Stream(static function () {
            yield ['id' => 1];
            yield ['id' => 2];
        }, recreatable: false);

        $firstPass = [];
        foreach ($stream as $row) {
            $firstPass[] = $row;
        }

        self::assertSame([['id' => 1], ['id' => 2]], $firstPass);
        self::assertTrue($stream->isConsumed());

        $this->expectException(StreamConsumedException::class);
        foreach ($stream as $row) {
            // unreachable
        }
    }
}

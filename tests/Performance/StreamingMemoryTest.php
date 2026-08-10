<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Performance;

use EreborCodeForge\Mazarbul\Pipeline\Pipeline;
use EreborCodeForge\Mazarbul\Stream\Stream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Validates that pull-based streaming does not grow memory linearly with row count.
 * Uses an in-memory generator (no DB) so CI can always run the shape of the check.
 */
#[Group('performance')]
final class StreamingMemoryTest extends TestCase
{
    public function testPipelineStreamingMemoryRemainsBounded(): void
    {
        $baseline = memory_get_usage(true);
        $peaks = [];

        foreach ([100_000, 500_000] as $rows) {
            $count = 0;
            $stream = new Stream(static function () use ($rows): \Generator {
                for ($i = 0; $i < $rows; ++$i) {
                    yield ['id' => $i, 'payload' => 'x'];
                }
            });

            Pipeline::from($stream)
                ->map(static function (mixed $row): int {
                    assert(is_array($row));
                    assert(is_numeric($row['id']));

                    return (int) $row['id'];
                })
                ->filter(static fn(mixed $id): bool => is_int($id) && ($id % 2) === 0)
                ->chunk(1000)
                ->each(static function (mixed $chunk) use (&$count): void {
                    assert(is_array($chunk));
                    $count += count($chunk);
                });

            self::assertSame((int) ($rows / 2), $count);
            $peaks[$rows] = memory_get_peak_usage(true) - $baseline;
        }

        // Growth from 100k -> 500k must stay well below linear (5x). Allow generous headroom.
        self::assertLessThan(
            $peaks[100_000] * 3 + 8_000_000,
            $peaks[500_000],
            sprintf(
                'Memory growth looks linear: 100k=%d bytes, 500k=%d bytes',
                $peaks[100_000],
                $peaks[500_000],
            ),
        );
    }
}

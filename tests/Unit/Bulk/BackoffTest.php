<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit\Bulk;

use EreborCodeForge\Mazarbul\Bulk\Backoff\ExponentialBackoff;
use EreborCodeForge\Mazarbul\Bulk\Backoff\FixedBackoff;
use EreborCodeForge\Mazarbul\Bulk\Backoff\NoBackoff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixedBackoff::class)]
#[CoversClass(ExponentialBackoff::class)]
#[CoversClass(NoBackoff::class)]
#[Group('unit')]
final class BackoffTest extends TestCase
{
    public function testNoBackoffAlwaysReturnsZero(): void
    {
        $backoff = new NoBackoff();

        self::assertSame(0.0, $backoff->delaySeconds(1));
        self::assertSame(0.0, $backoff->delaySeconds(5));
    }

    public function testFixedBackoffReturnsConstantDelay(): void
    {
        $backoff = new FixedBackoff(0.35);

        self::assertSame(0.35, $backoff->delaySeconds(1));
        self::assertSame(0.35, $backoff->delaySeconds(2));
        self::assertSame(0.35, $backoff->delaySeconds(10));
    }

    public function testExponentialBackoffWithoutJitter(): void
    {
        $backoff = new ExponentialBackoff(
            baseSeconds: 0.05,
            multiplier: 2.0,
            maxSeconds: 2.0,
            jitter: false,
        );

        self::assertEqualsWithDelta(0.05, $backoff->delaySeconds(1), 1e-9);
        self::assertEqualsWithDelta(0.10, $backoff->delaySeconds(2), 1e-9);
        self::assertEqualsWithDelta(0.20, $backoff->delaySeconds(3), 1e-9);
        self::assertEqualsWithDelta(0.40, $backoff->delaySeconds(4), 1e-9);
    }

    public function testExponentialBackoffRespectsMaxSeconds(): void
    {
        $backoff = new ExponentialBackoff(
            baseSeconds: 1.0,
            multiplier: 2.0,
            maxSeconds: 1.5,
            jitter: false,
        );

        self::assertEqualsWithDelta(1.0, $backoff->delaySeconds(1), 1e-9);
        self::assertEqualsWithDelta(1.5, $backoff->delaySeconds(2), 1e-9);
        self::assertEqualsWithDelta(1.5, $backoff->delaySeconds(8), 1e-9);
    }

    public function testExponentialBackoffWithJitterStaysWithinHalfToFullDelay(): void
    {
        $backoff = new ExponentialBackoff(
            baseSeconds: 1.0,
            multiplier: 2.0,
            maxSeconds: 10.0,
            jitter: true,
        );

        for ($i = 0; $i < 20; ++$i) {
            $delay = $backoff->delaySeconds(1);
            self::assertGreaterThanOrEqual(0.5, $delay);
            self::assertLessThanOrEqual(1.0, $delay);
        }
    }
}

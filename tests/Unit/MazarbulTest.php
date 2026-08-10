<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Unit;

use EreborCodeForge\Mazarbul\Mazarbul;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mazarbul::class)]
final class MazarbulTest extends TestCase
{
    public function testVersionIsDefined(): void
    {
        self::assertNotSame('', Mazarbul::VERSION);
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

use EreborCodeForge\Mazarbul\Pipeline\Pipeline;
use IteratorAggregate;
use Traversable;

/**
 * @extends IteratorAggregate<int|string, mixed>
 */
interface ResultStream extends IteratorAggregate
{
    public function close(): void;

    public function isConsumed(): bool;

    public function pipeline(): Pipeline;

    /**
     * @return Traversable<int|string, mixed>
     */
    public function getIterator(): Traversable;
}

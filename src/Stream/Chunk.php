<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Stream;

/**
 * @template T
 */
final readonly class Chunk
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $index,
    ) {
    }

    public function count(): int
    {
        return count($this->items);
    }
}

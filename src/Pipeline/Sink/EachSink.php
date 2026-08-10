<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Sink;

use Closure;
use EreborCodeForge\Mazarbul\Contract\Sink;

/**
 * @implements Sink<null>
 */
final class EachSink implements Sink
{
    /**
     * @param Closure(mixed): void $consumer
     */
    public function __construct(
        private readonly Closure $consumer,
    ) {
    }

    public function consume(iterable $input): mixed
    {
        foreach ($input as $item) {
            ($this->consumer)($item);
        }

        return null;
    }
}

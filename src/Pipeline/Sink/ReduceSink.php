<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Sink;

use Closure;
use EreborCodeForge\Mazarbul\Contract\Sink;

/**
 * @implements Sink<mixed>
 */
final class ReduceSink implements Sink
{
    /**
     * @param Closure(mixed, mixed): mixed $reducer
     */
    public function __construct(
        private readonly Closure $reducer,
        private readonly mixed $initial = null,
    ) {
    }

    public function consume(iterable $input): mixed
    {
        $carry = $this->initial;
        foreach ($input as $item) {
            $carry = ($this->reducer)($carry, $item);
        }

        return $carry;
    }
}

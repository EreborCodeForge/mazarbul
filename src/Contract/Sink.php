<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

/**
 * @template TResult
 */
interface Sink
{
    /**
     * @param iterable<mixed> $input
     *
     * @return TResult
     */
    public function consume(iterable $input): mixed;
}

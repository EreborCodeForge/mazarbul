<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Stage;

use Closure;
use EreborCodeForge\Mazarbul\Contract\PipelineStage;

final class FlatMapStage implements PipelineStage
{
    /**
     * @param Closure(mixed): iterable<mixed> $mapper
     */
    public function __construct(
        private readonly Closure $mapper,
    ) {
    }

    public function apply(iterable $input): iterable
    {
        foreach ($input as $item) {
            yield from ($this->mapper)($item);
        }
    }
}

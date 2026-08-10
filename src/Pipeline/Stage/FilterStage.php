<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Stage;

use Closure;
use EreborCodeForge\Mazarbul\Contract\PipelineStage;

final class FilterStage implements PipelineStage
{
    /**
     * @param Closure(mixed): bool $predicate
     */
    public function __construct(
        private readonly Closure $predicate,
    ) {
    }

    public function apply(iterable $input): iterable
    {
        foreach ($input as $item) {
            if (($this->predicate)($item)) {
                yield $item;
            }
        }
    }
}

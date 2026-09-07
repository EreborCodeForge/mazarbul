<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Stage;

use Closure;
use EreborCodeForge\Mazarbul\Contract\PipelineStage;

final class MapStage implements PipelineStage
{
    /**
     * @param Closure(mixed): mixed $mapper
     */
    public function __construct(
        private readonly Closure $mapper,
    ) {
    }

    /**
     * @return Closure(mixed): mixed
     */
    public function mapper(): Closure
    {
        return $this->mapper;
    }

    public function apply(iterable $input): iterable
    {
        foreach ($input as $item) {
            yield ($this->mapper)($item);
        }
    }
}

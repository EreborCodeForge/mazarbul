<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Stage;

use Closure;
use EreborCodeForge\Mazarbul\Contract\PipelineStage;

final class TapStage implements PipelineStage
{
    /**
     * @param Closure(mixed): void $callback
     */
    public function __construct(
        private readonly Closure $callback,
    ) {
    }

    /**
     * @return Closure(mixed): void
     */
    public function callback(): Closure
    {
        return $this->callback;
    }

    public function apply(iterable $input): iterable
    {
        foreach ($input as $item) {
            ($this->callback)($item);
            yield $item;
        }
    }
}

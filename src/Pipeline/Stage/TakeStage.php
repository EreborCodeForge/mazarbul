<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Stage;

use EreborCodeForge\Mazarbul\Contract\PipelineStage;
use EreborCodeForge\Mazarbul\Exception\PipelineException;

final class TakeStage implements PipelineStage
{
    public function __construct(
        private readonly int $limit,
    ) {
        if ($this->limit < 0) {
            throw PipelineException::invalidTakeLimit($this->limit);
        }
    }

    public function apply(iterable $input): iterable
    {
        if ($this->limit === 0) {
            return;
        }

        $taken = 0;
        foreach ($input as $item) {
            yield $item;
            ++$taken;
            if ($taken >= $this->limit) {
                break;
            }
        }
    }
}

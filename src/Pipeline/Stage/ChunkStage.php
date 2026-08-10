<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Stage;

use EreborCodeForge\Mazarbul\Contract\PipelineStage;
use EreborCodeForge\Mazarbul\Exception\PipelineException;

final class ChunkStage implements PipelineStage
{
    public function __construct(
        private readonly int $chunkSize,
    ) {
        if ($this->chunkSize < 1) {
            throw PipelineException::invalidChunkSize($this->chunkSize);
        }
    }

    public function apply(iterable $input): iterable
    {
        $buffer = [];
        foreach ($input as $item) {
            $buffer[] = $item;
            if (count($buffer) >= $this->chunkSize) {
                yield $buffer;
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            yield $buffer;
        }
    }
}

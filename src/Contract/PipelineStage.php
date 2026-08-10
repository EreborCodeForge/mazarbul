<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

interface PipelineStage
{
    /**
     * @param iterable<mixed> $input
     *
     * @return iterable<mixed>
     */
    public function apply(iterable $input): iterable;
}

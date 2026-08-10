<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Sink;

use EreborCodeForge\Mazarbul\Contract\Sink;

/**
 * @implements Sink<list<mixed>>
 */
final class CollectSink implements Sink
{
    public function __construct(
        private readonly ?int $limit = null,
    ) {
    }

    public function consume(iterable $input): mixed
    {
        $result = [];
        foreach ($input as $item) {
            $result[] = $item;
            if ($this->limit !== null && count($result) >= $this->limit) {
                break;
            }
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Stream;

use Closure;
use EreborCodeForge\Mazarbul\Contract\ResultStream;
use EreborCodeForge\Mazarbul\Exception\StreamConsumedException;
use EreborCodeForge\Mazarbul\Pipeline\Pipeline;
use Generator;
use Traversable;

/**
 * Generic recreatable stream backed by a source factory.
 */
final class Stream implements ResultStream
{
    private StreamState $state = StreamState::Ready;

    /**
     * @param Closure(): iterable<mixed> $source
     */
    public function __construct(
        private readonly Closure $source,
        private readonly bool $recreatable = true,
    ) {
    }

    public function close(): void
    {
        $this->state = StreamState::Closed;
    }

    public function isConsumed(): bool
    {
        return $this->state === StreamState::Consumed || $this->state === StreamState::Closed;
    }

    public function pipeline(): Pipeline
    {
        return Pipeline::from($this);
    }

    public function getIterator(): Traversable
    {
        if ($this->state === StreamState::Consumed && !$this->recreatable) {
            throw StreamConsumedException::create();
        }

        if ($this->state === StreamState::Closed) {
            throw StreamConsumedException::create();
        }

        $this->state = StreamState::Open;

        try {
            foreach (($this->source)() as $key => $value) {
                if (is_int($key) || is_string($key)) {
                    yield $key => $value;
                } else {
                    yield $value;
                }
            }
        } finally {
            $this->state = $this->recreatable ? StreamState::Ready : StreamState::Consumed;
        }
    }
}

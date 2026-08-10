<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use EreborCodeForge\Mazarbul\Bulk\Backoff\ExponentialBackoff;
use EreborCodeForge\Mazarbul\Bulk\Backoff\NoBackoff;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 1,
        public BackoffStrategy $backoff = new NoBackoff(),
        public RetryDecider $decider = new RetryDecider(),
        public bool $allowRetryWithoutTransaction = false,
    ) {
    }

    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    public static function transient(int $maxAttempts = 3): self
    {
        return new self(
            maxAttempts: max(1, $maxAttempts),
            backoff: new ExponentialBackoff(),
            decider: new RetryDecider(),
        );
    }

    public function withMaxAttempts(int $maxAttempts): self
    {
        return clone($this, ['maxAttempts' => max(1, $maxAttempts)]);
    }
}

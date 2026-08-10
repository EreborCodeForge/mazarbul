<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

final readonly class LifecyclePolicy
{
    public function __construct(
        public bool $lazy = true,
        public ?int $idleTimeoutSeconds = 60,
        public ?int $maxLifetimeSeconds = 600,
        public ?int $healthCheckIntervalSeconds = 30,
        public bool $reconnectOnFailure = true,
    ) {
    }

    public function withIdleTimeoutSeconds(?int $seconds): self
    {
        return clone($this, ['idleTimeoutSeconds' => $seconds]);
    }

    public function withMaxLifetimeSeconds(?int $seconds): self
    {
        return clone($this, ['maxLifetimeSeconds' => $seconds]);
    }

    public function withHealthCheckIntervalSeconds(?int $seconds): self
    {
        return clone($this, ['healthCheckIntervalSeconds' => $seconds]);
    }
}

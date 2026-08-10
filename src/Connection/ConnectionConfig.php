<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

/**
 * Immutable connection configuration. Does not open a physical connection.
 */
final readonly class ConnectionConfig
{
    /**
     * @param array<int, mixed> $options PDO attributes
     */
    public function __construct(
        public string $dsn,
        public ?string $username = null,
        public ?string $password = null,
        public array $options = [],
        public LifecyclePolicy $lifecycle = new LifecyclePolicy(),
    ) {
    }

    public function withLifecycle(LifecyclePolicy $lifecycle): self
    {
        return clone($this, ['lifecycle' => $lifecycle]);
    }

    /**
     * @param array<int, mixed> $options
     */
    public function withOptions(array $options): self
    {
        return clone($this, ['options' => $options]);
    }
}

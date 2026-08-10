<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Support;

use Closure;
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Contract\ConnectionFactory;
use PDO;

final class CountingConnectionFactory implements ConnectionFactory
{
    public private(set) int $createCount = 0;

    /** @var list<PDO> */
    public private(set) array $created = [];

    /**
     * @param Closure(ConnectionConfig): PDO|null $factory
     */
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?Closure $factory = null,
    ) {
    }

    public function create(ConnectionConfig $config): PDO
    {
        ++$this->createCount;

        if ($this->factory !== null) {
            $pdo = ($this->factory)($config);
            $this->created[] = $pdo;

            return $pdo;
        }

        if ($this->pdo !== null) {
            $this->created[] = $this->pdo;

            return $this->pdo;
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->created[] = $pdo;

        return $pdo;
    }
}

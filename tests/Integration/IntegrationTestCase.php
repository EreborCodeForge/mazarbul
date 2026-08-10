<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Integration;

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;
use EreborCodeForge\Mazarbul\Exception\ConnectionException;
use EreborCodeForge\Mazarbul\Exception\QueryException;
use EreborCodeForge\Mazarbul\Query\Database;
use EreborCodeForge\Mazarbul\Tests\Support\SpyObserver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PDOException;

#[Group('integration')]
abstract class IntegrationTestCase extends TestCase
{
    protected ConnectionManager $manager;
    protected SpyObserver $observer;
    protected Database $db;

    abstract protected function dsn(): string;

    abstract protected function username(): string;

    abstract protected function password(): string;

    abstract protected function driver(): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->observer = new SpyObserver();
        $this->manager = new ConnectionManager(
            factory: new PdoConnectionFactory(),
            observer: $this->observer,
        );

        try {
            $this->manager->define('default', new ConnectionConfig(
                dsn: $this->dsn(),
                username: $this->username(),
                password: $this->password(),
                options: [
                    \PDO::ATTR_TIMEOUT => 2,
                ],
                lifecycle: new LifecyclePolicy(
                    idleTimeoutSeconds: null,
                    maxLifetimeSeconds: null,
                    healthCheckIntervalSeconds: null,
                ),
            ));
            $this->db = $this->manager->database('default');
            $this->db->execute('SELECT 1');
        } catch (ConnectionException|QueryException|PDOException $e) {
            self::markTestSkipped('Database unavailable for integration tests: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $this->manager->onRequestEnd();
        $this->manager->onWorkerStop();
        parent::tearDown();
    }
}

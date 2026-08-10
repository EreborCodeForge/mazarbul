<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;

$manager = new ConnectionManager(new PdoConnectionFactory());
$manager->define('default', new ConnectionConfig(
    dsn: getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=mazarbul;charset=utf8mb4',
    username: getenv('DB_USER') ?: 'mazarbul',
    password: getenv('DB_PASSWORD') ?: 'mazarbul',
    lifecycle: new LifecyclePolicy(
        idleTimeoutSeconds: 60,
        maxLifetimeSeconds: 600,
        healthCheckIntervalSeconds: 30,
    ),
));

$manager->onWorkerStart();

for ($job = 0; $job < 3; ++$job) {
    $manager->onRequestStart();
    try {
        $db = $manager->database('default');
        $value = $db->scalar('SELECT 1');
        echo "job={$job} value={$value}", PHP_EOL;
    } finally {
        $manager->onRequestEnd();
    }
}

$manager->onWorkerStop();

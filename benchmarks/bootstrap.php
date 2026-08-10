<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\LifecyclePolicy;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;
use EreborCodeForge\Mazarbul\Query\Database;

function mazarbul_benchmark_db(): Database
{
    $manager = new ConnectionManager(new PdoConnectionFactory());
    $manager->define('default', new ConnectionConfig(
        dsn: getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=mazarbul;charset=utf8mb4',
        username: getenv('DB_USER') ?: 'mazarbul',
        password: getenv('DB_PASSWORD') ?: 'mazarbul',
        lifecycle: new LifecyclePolicy(
            idleTimeoutSeconds: null,
            maxLifetimeSeconds: null,
            healthCheckIntervalSeconds: null,
        ),
    ));

    return $manager->database('default');
}

/**
 * @return array{elapsed: float, peak_memory: int, rows_per_sec: float}
 */
function mazarbul_bench(string $label, int $rows, callable $fn): array
{
    $startMem = memory_get_usage(true);
    $start = microtime(true);
    $fn();
    $elapsed = microtime(true) - $start;
    $peak = memory_get_peak_usage(true) - $startMem;

    $result = [
        'elapsed' => $elapsed,
        'peak_memory' => $peak,
        'rows_per_sec' => $elapsed > 0 ? $rows / $elapsed : 0.0,
    ];

    echo sprintf(
        "%s: elapsed=%.3fs peak_delta=%dB rows/s=%.0f\n",
        $label,
        $result['elapsed'],
        $result['peak_memory'],
        $result['rows_per_sec'],
    );

    return $result;
}

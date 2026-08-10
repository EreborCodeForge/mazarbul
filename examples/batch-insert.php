<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Bulk\RetryPolicy;
use EreborCodeForge\Mazarbul\Bulk\TransactionMode;
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;

$manager = new ConnectionManager(new PdoConnectionFactory());
$manager->define('default', new ConnectionConfig(
    dsn: getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=mazarbul;charset=utf8mb4',
    username: getenv('DB_USER') ?: 'mazarbul',
    password: getenv('DB_PASSWORD') ?: 'mazarbul',
));

$db = $manager->database('default');

$rows = (static function (): \Generator {
    for ($i = 0; $i < 10_000; ++$i) {
        yield ['name' => 'User ' . $i, 'email' => 'user' . $i . '@example.test'];
    }
})();

$count = $db->bulk()->insert(
    table: 'users',
    columns: ['name', 'email'],
    rows: $rows,
    options: new BulkOptions(
        chunkSize: 1000,
        transactionMode: TransactionMode::PER_CHUNK,
        retryPolicy: RetryPolicy::transient(3),
    ),
);

echo "inserted={$count}", PHP_EOL;

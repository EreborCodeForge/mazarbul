<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

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

foreach ($db->stream('SELECT id, email FROM users') as $row) {
    echo $row['id'], ' ', $row['email'], PHP_EOL;
}

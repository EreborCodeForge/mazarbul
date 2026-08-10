<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Bulk\TransactionMode;

$db = mazarbul_benchmark_db();
$db->execute('DROP TABLE IF EXISTS bench_bulk');
$db->execute('CREATE TABLE bench_bulk (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(64) NOT NULL)');

$rows = 10_000;

$generator = static function () use ($rows): \Generator {
    for ($i = 0; $i < $rows; ++$i) {
        yield ['name' => 'n' . $i];
    }
};

foreach ([TransactionMode::NONE, TransactionMode::PER_CHUNK, TransactionMode::ALL] as $mode) {
    $db->execute('TRUNCATE TABLE bench_bulk');
    mazarbul_bench('bulk insert ' . $mode->name, $rows, static function () use ($db, $generator, $mode): void {
        (void) $db->bulk()->insert(
            table: 'bench_bulk',
            columns: ['name'],
            rows: $generator(),
            options: new BulkOptions(chunkSize: 500, transactionMode: $mode),
        );
    });
}

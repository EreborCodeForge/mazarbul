<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$db = mazarbul_benchmark_db();
$db->execute('DROP TABLE IF EXISTS bench_logs');
$db->execute('CREATE TABLE bench_logs (id INT AUTO_INCREMENT PRIMARY KEY, payload VARCHAR(32) NOT NULL)');

$rows = 20_000;
$chunk = [];
for ($i = 0; $i < $rows; ++$i) {
    $chunk[] = ['payload' => 'row-' . $i];
    if (count($chunk) === 500) {
        $db->bulk()->insert('bench_logs', ['payload'], $chunk);
        $chunk = [];
    }
}
if ($chunk !== []) {
    $db->bulk()->insert('bench_logs', ['payload'], $chunk);
}

mazarbul_bench('fetchAll', $rows, static function () use ($db): void {
    $all = $db->fetchAll('SELECT id, payload FROM bench_logs');
    unset($all);
});

mazarbul_bench('stream', $rows, static function () use ($db): void {
    $n = 0;
    foreach ($db->stream('SELECT id, payload FROM bench_logs') as $row) {
        ++$n;
    }
    unset($n);
});

mazarbul_bench('stream+map', $rows, static function () use ($db): void {
    $db->stream('SELECT id, payload FROM bench_logs')
        ->pipeline()
        ->map(static fn(array $row): string => (string) $row['payload'])
        ->each(static function (): void {
        });
});

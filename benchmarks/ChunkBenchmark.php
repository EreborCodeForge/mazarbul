<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$db = mazarbul_benchmark_db();
$db->execute('DROP TABLE IF EXISTS bench_events');
$db->execute('CREATE TABLE bench_events (id INT AUTO_INCREMENT PRIMARY KEY, payload VARCHAR(32) NOT NULL)');

$rows = 20_000;
$buffer = [];
for ($i = 0; $i < $rows; ++$i) {
    $buffer[] = ['payload' => 'evt-' . $i];
    if (count($buffer) === 500) {
        $db->bulk()->insert('bench_events', ['payload'], $buffer);
        $buffer = [];
    }
}
if ($buffer !== []) {
    $db->bulk()->insert('bench_events', ['payload'], $buffer);
}

mazarbul_bench('stream+chunk(1000)', $rows, static function () use ($db): void {
    $db->stream('SELECT id, payload FROM bench_events')
        ->pipeline()
        ->chunk(1000)
        ->each(static function (array $chunk): void {
            unset($chunk);
        });
});

# Mazarbul benchmarks

Run against a local database. Credentials must come from the environment.

```bash
export DB_DSN='mysql:host=127.0.0.1;dbname=mazarbul;charset=utf8mb4'
export DB_USER=mazarbul
export DB_PASSWORD=mazarbul

php benchmarks/StreamBenchmark.php
php benchmarks/ChunkBenchmark.php
php benchmarks/BulkInsertBenchmark.php
```

Do not commit large generated dumps. Summarize results manually when needed.

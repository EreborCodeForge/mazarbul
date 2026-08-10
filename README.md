# Mazarbul

Stream-oriented database access for PHP 8.5+ with lazy reusable connections, composable pipelines, chunk processing, and driver-aware bulk writes.

**Package:** `ereborcodeforge/mazarbul`  
**Namespace:** `EreborCodeForge\Mazarbul`

## Requirements

- PHP 8.5+
- `ext-pdo` (plus `pdo_mysql` / `pdo_pgsql` for those drivers)

## Install

```bash
composer require ereborcodeforge/mazarbul
```

## Quick start

```php
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;

$manager = new ConnectionManager(new PdoConnectionFactory());

$manager->define('default', new ConnectionConfig(
    dsn: $_ENV['DB_DSN'],
    username: $_ENV['DB_USER'],
    password: $_ENV['DB_PASSWORD'],
));

$db = $manager->database('default'); // still no I/O

$user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);

foreach ($db->stream('SELECT * FROM logs') as $row) {
    // one row at a time
}

$db->stream('SELECT id, email FROM users')
    ->pipeline()
    ->filter(static fn(array $row): bool => $row['email'] !== null)
    ->map(static fn(array $row): array => [
        ...$row,
        'email' => strtolower((string) $row['email']),
    ])
    ->chunk(500)
    ->each($processChunk);
```

## Design highlights

- Lazy connection lifecycle for long-running workers
- Traditional CRUD API for small result sets
- True streaming reads with driver-aware buffering strategy
- Lazy pull-based pipelines (`map` / `filter` / `chunk` / …)
- Bulk insert/update/delete over `iterable` with transaction modes and retries
- Framework-agnostic (no ORM, no Active Record)

## Development

```bash
composer install
composer test
composer analyse
```

Integration tests (requires Docker services):

```bash
docker compose up -d
composer test:integration
```

Copy [`.env.example`](.env.example) to `.env` for local credentials. Never commit real secrets.

If credentials were ever exposed in repository history, rotate them immediately.

## Documentation

See [`docs/`](docs/) for architecture, connections, streaming, pipelines, bulk operations, transactions, and benchmarks.

## Releases & Packagist

Versions follow git tags (`v1.0.0`, `v1.1.0`, …). Pushing a tag runs CI, creates a GitHub Release, and can notify Packagist.

**One-time Packagist setup**

1. Submit the repository at [packagist.org/packages/submit](https://packagist.org/packages/submit) with  
   `https://github.com/EreborCodeForge/mazarbul`
2. In Packagist → package settings, enable the **GitHub Hook** (recommended), **or**
3. Add repository secrets `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` so `.github/workflows/release.yml` calls the Packagist update API on each tag

After that, consumers get new versions with:

```bash
composer update ereborcodeforge/mazarbul
```

## License

MIT

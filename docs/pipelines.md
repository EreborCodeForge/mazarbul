# Pipelines

Pipelines transform any `iterable` lazily.

```php
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

Stages: `map`, `filter`, `tap`, `flatMap`, `take`, `chunk`, `through(PipelineStage)`.

Built-in `map` / `filter` / `tap` / `take` may run on an internal fused path (same semantics). `chunk`, `flatMap`, and `through(custom)` keep the nested stage path.

Terminal sinks: `each`, `collect`, `first`, `reduce`, `toBatchInsert`, `toBatchUpdate`.

`collect()` materializes memory on purpose — prefer `each` / chunk sinks for large data.

### Double-chunk warning

`->chunk(n)->toBatchInsert(...)` can buffer twice (pipeline chunks, then bulk chunks again). Prefer the sink chunk size directly:

```php
$pipeline->toBatchInsert($db, 'users', ['id', 'email'], chunkSize: 500);
```

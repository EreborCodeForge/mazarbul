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

Terminal sinks: `each`, `collect`, `first`, `reduce`, `toBatchInsert`, `toBatchUpdate`.

`collect()` materializes memory on purpose — prefer `each` / chunk sinks for large data.

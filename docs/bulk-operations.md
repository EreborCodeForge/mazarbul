# Bulk operations

```php
$count = $db->bulk()->insert(
    table: 'users',
    columns: ['name', 'email'],
    rows: $generator,
    options: new BulkOptions(
        chunkSize: 1000,
        transactionMode: TransactionMode::PER_CHUNK,
        retryPolicy: RetryPolicy::transient(maxAttempts: 3),
    ),
);
```

## Transaction modes

| Mode | Behavior |
|------|----------|
| `NONE` | No automatic transaction; partial success possible |
| `PER_CHUNK` | One transaction per chunk; safest default for retries |
| `ALL` | One transaction for the whole operation |

Effective chunk size is capped by `floor(maxBindParameters / paramsPerRow)`.

Updates use driver strategies (MySQL `CASE/WHEN`, PostgreSQL `UPDATE ... FROM (VALUES ...)`).

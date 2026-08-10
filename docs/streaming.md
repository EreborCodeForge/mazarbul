# Streaming

```php
foreach ($db->stream('SELECT * FROM logs') as $row) {
    process($row);
}
```

`DatabaseResultStream` is single-pass. Re-iterating throws `StreamConsumedException`.

## Driver notes

- **MySQL:** configures `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` for unbuffered reads when the PDO driver is mysql.
- **PostgreSQL:** v1 uses incremental `fetch()`; PDO pgsql typically buffers client-side. Documented limitation — server-side cursors can be added later behind `StreamConfigurator`.

Always closes cursors in `finally` (normal completion, early `break`, or exception).

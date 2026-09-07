# Streaming

```php
foreach ($db->stream('SELECT * FROM logs') as $row) {
    process($row);
}
```

`DatabaseResultStream` is single-pass. Re-iterating throws `StreamConsumedException`.

## Driver notes

- **MySQL:** configures `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` for unbuffered reads when the PDO driver is mysql.
- **PostgreSQL:** uses a server-side cursor (`DECLARE … NO SCROLL CURSOR` + `FETCH FORWARD`) inside a transaction (opened by the stream when none is active). Rows are pulled incrementally; the cursor is `CLOSE`d in `finally` (normal completion, early `break`, or exception).

Always closes cursors in `finally` (normal completion, early `break`, or exception).

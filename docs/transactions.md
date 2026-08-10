# Transactions

```php
$result = $db->transaction(function (Database $db) {
    $db->execute('UPDATE accounts SET balance = balance - ? WHERE id = ?', [10, 1]);
    return $db->fetchOne('SELECT * FROM accounts WHERE id = ?', [1]);
});
```

- Commits on success
- Rolls back on exception and rethrows the original error
- Nested transactions throw `TransactionException` in v1
- `onRequestEnd()` rolls back leaked transactions

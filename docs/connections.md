# Connections

```php
$manager = new ConnectionManager(new PdoConnectionFactory());
$manager->define('default', new ConnectionConfig(
    dsn: $_ENV['DB_DSN'],
    username: $_ENV['DB_USER'],
    password: $_ENV['DB_PASSWORD'],
    lifecycle: new LifecyclePolicy(
        idleTimeoutSeconds: 60,
        maxLifetimeSeconds: 600,
        healthCheckIntervalSeconds: 30,
    ),
));

$db = $manager->database('default'); // still no I/O
```

## Worker hooks

```php
$manager->onWorkerStart();
$manager->onRequestStart();
// handle job/request
$manager->onRequestEnd(); // rolls back leaked transactions, applies idle/lifetime policy
$manager->onWorkerStop();
```

Supported drivers: `mysql`, `pgsql`. Unknown drivers throw `UnsupportedDriverException`.

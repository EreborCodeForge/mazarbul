# Mazarbul — Implementation & Refactoring Specification

> **Package:** `ereborcodeforge/mazarbul`  
> **Root namespace:** `EreborCodeForge\Mazarbul`  
> **Language:** PHP 8.3+  
> **Status:** Architecture / implementation specification  
> **Primary goal:** evolve the existing stream/database prototype into a production-ready, stream-oriented database access library with lazy connection lifecycle, traditional access, pipelines, chunk processing, bulk writes, retries, transactions, observability hooks, and automated tests.

---

## 1. Product definition

Mazarbul is a **stream-oriented database access layer for PHP**.

The library must support two equally important usage models:

1. **Traditional database access** for ordinary CRUD and small result sets.
2. **Streaming/pipeline access** for large datasets, long-running processes, workers, ETL-style workloads, chunk processing, and batch writes.

Mazarbul must be framework-agnostic and must not depend on Durin's Forge or MithrilPHP. Those projects may integrate Mazarbul through adapters/service providers later.

### Product statement

> Mazarbul provides lazy and reusable database connections, memory-efficient streaming reads, composable pipelines, chunked processing, transactional operations, and driver-aware bulk writes while preserving a simple traditional database API.

### Core principles

- Streaming is a first-class capability, not an afterthought.
- Traditional access remains simple.
- Connection lifecycle is explicit and safe for long-running PHP workers.
- `Generator`/`yield` must be used where they actually reduce memory usage.
- Streaming must not claim to be memory efficient when the driver buffers the full result set.
- Pipelines transform streams; database drivers do not own pipeline business logic.
- Bulk operations operate over `iterable`, never requiring the complete dataset in memory.
- Driver-specific SQL belongs behind dialect/strategy abstractions.
- PDO is an implementation detail behind contracts where practical.
- No ORM in the first versions.
- No Active Record.
- No global mutable singleton/static connection registry.
- Prefer composition over large god objects.
- Public APIs should be difficult to misuse.

---

# 2. Validation of the current prototype

The supplied source contains the following relevant implementation:

```text
src/
├── BulkExecutor.php
├── BulkRetryPolicy.php
├── BulkTransactionMode.php
├── DatabaseConnectionConfig.php
├── DatabaseConnectionManager.php
├── DatabaseStream.php
├── Dialect/
│   ├── MySqlDialect.php
│   ├── PostgresDialect.php
│   ├── SqlDialect.php
│   └── SqlDialectFactory.php
├── Exception/
│   ├── DatabaseException.php
│   └── DatabaseStreamException.php
├── benchmark.php
├── benchmark2.php
├── benchmark3.php
└── benchmark4.php
```

The current prototype is useful and should be treated as **behavioral reference**, not as the final package architecture.

## 2.1 What should be preserved conceptually

### DatabaseStream

Useful existing ideas:

- lazy result consumption;
- `Generator` based reading;
- `map()`;
- `filter()`;
- `chunked()`;
- `forEach()`;
- `reduce()`;
- `first()`;
- `toArray()`;
- connection selection;
- traditional prepared execution.

These capabilities should remain, but the class currently has too many responsibilities.

### BulkExecutor

Useful existing ideas:

- accepts `iterable` rows;
- chunk-based INSERT;
- chunk-based UPDATE;
- chunk-based DELETE;
- transaction modes;
- deterministic fault injection;
- retry policy;
- backoff;
- SQL dialect abstraction;
- CASE-based bulk update strategy.

These are solid concepts and should evolve into dedicated components instead of being discarded.

### Dialects

The current `SqlDialect` abstraction is a good start. Identifier validation/quoting should be preserved and expanded.

---

# 3. Problems found in the current implementation

The implementation agent MUST address the following before considering the package production-ready.

## 3.1 Security blocker: credentials committed in benchmark source

At least one benchmark contains a real-looking remote database host, username, and plaintext password.

Required actions:

- remove all credentials from repository files;
- never include credentials in benchmark fixtures;
- load integration credentials from environment variables;
- add `.env.example` with fake values only;
- add `.env` to `.gitignore`;
- document that any credential already exposed in source/history must be rotated;
- ensure CI does not print secrets;
- optionally add secret scanning to CI.

This is a **P0 blocker**.

## 3.2 Current connection manager is eager and globally static

Current behavior:

```php
DatabaseConnectionManager::addConnection(...)
```

creates PDO immediately and stores it in:

```php
private static array $connections = [];
```

Problems:

- connection is opened eagerly;
- global mutable state;
- difficult isolation in tests;
- lifecycle cannot be scoped;
- worker reset/reconnect policy is difficult;
- dependency injection becomes awkward;
- config and physical connection are coupled.

Replace with an **instance-based `ConnectionManager`** that stores definitions/configurations and materializes connections lazily.

## 3.3 `DatabaseStream` is a god object candidate

It currently mixes:

- query building state;
- prepared query execution;
- raw execution;
- streaming;
- iterator implementation;
- transformations;
- reducers;
- bulk insert/update/delete;
- connection lookup.

Split into:

- database/client facade;
- query executor;
- result stream;
- generic pipeline;
- bulk writer;
- connection manager.

## 3.4 Current BulkExecutor construction is inconsistent

`BulkExecutor` requires:

```php
PDO $pdo,
SqlDialect $dialect,
?Closure $faultInjector = null
```

but current call sites instantiate it with incompatible arguments in places, including construction with only PDO and benchmark code that passes a closure where a dialect is expected.

The new design must make invalid construction impossible by wiring dependencies through factories/manager objects.

## 3.5 Exceptions are not consistently namespaced

The current exception files do not define the same namespace as the rest of the package.

All public exceptions must live under:

```text
EreborCodeForge\Mazarbul\Exception
```

## 3.6 Dialect factory silently defaults to MySQL

Unknown DSNs currently fall back to `MySqlDialect`.

This is dangerous.

Required behavior:

- explicit supported driver detection;
- throw `UnsupportedDriverException` for unknown DSN/driver;
- never silently choose a SQL dialect.

## 3.7 Generator streams should be considered single-pass

Methods such as `count()`, `first()`, and `toArray()` consume iterators/generators.

A consumed PHP generator cannot safely be treated as a rewindable collection.

The new API must explicitly model streams as **single-pass** unless created from a reusable source factory.

Avoid implementing `Iterator` directly on a high-level database object.

Prefer:

```php
final class Stream implements IteratorAggregate
{
    /** @var Closure(): iterable */
    private Closure $source;
}
```

A source factory allows deliberate recreation when supported, while cursor-based DB streams may still enforce single consumption.

## 3.8 Streaming must be driver-aware

`yield` alone does not guarantee database streaming.

For MySQL/PDO, buffered queries can cause the driver to load the result despite yielding one row at a time.

The implementation must provide a driver capability layer and use unbuffered/cursor configuration when supported.

The tests/benchmarks must validate peak memory behavior.

## 3.9 Bulk retry semantics need stronger correctness guarantees

Retries involving writes can duplicate effects if a failed operation's commit state is unknown.

Requirements:

- retry only errors classified as retryable;
- document idempotency requirements;
- default policy should be conservative;
- retries inside `PER_CHUNK` transaction can be supported;
- retries with transaction mode `NONE` require explicit opt-in/documented semantics;
- transaction state must always be cleaned up before retry;
- expose attempt/chunk metadata to observers.

---

# 4. Target package structure

Create the package with this target structure.

```text
mazarbul/
├── composer.json
├── README.md
├── LICENSE
├── phpunit.xml
├── phpstan.neon
├── rector.php                       # optional, only if adopted
├── .editorconfig
├── .gitignore
├── .env.example
├── .github/
│   └── workflows/
│       └── ci.yml
├── docs/
│   ├── architecture.md
│   ├── connections.md
│   ├── streaming.md
│   ├── pipelines.md
│   ├── bulk-operations.md
│   ├── transactions.md
│   └── benchmarks.md
├── benchmarks/
│   ├── StreamBenchmark.php
│   ├── ChunkBenchmark.php
│   ├── BulkInsertBenchmark.php
│   └── README.md
├── examples/
│   ├── traditional.php
│   ├── streaming.php
│   ├── pipeline.php
│   ├── batch-insert.php
│   └── long-running-worker.php
├── src/
│   ├── Mazarbul.php
│   ├── Contract/
│   │   ├── Connection.php
│   │   ├── ConnectionFactory.php
│   │   ├── ConnectionLifecycle.php
│   │   ├── Dialect.php
│   │   ├── QueryExecutor.php
│   │   ├── ResultStream.php
│   │   ├── PipelineStage.php
│   │   ├── Sink.php
│   │   └── Observer.php
│   ├── Connection/
│   │   ├── ConnectionConfig.php
│   │   ├── ConnectionDefinition.php
│   │   ├── ConnectionManager.php
│   │   ├── ManagedConnection.php
│   │   ├── PdoConnection.php
│   │   ├── PdoConnectionFactory.php
│   │   ├── ConnectionHealth.php
│   │   ├── ConnectionState.php
│   │   └── LifecyclePolicy.php
│   ├── Driver/
│   │   ├── Driver.php
│   │   ├── DriverCapabilities.php
│   │   ├── DriverResolver.php
│   │   ├── MySql/
│   │   │   ├── MySqlDialect.php
│   │   │   ├── MySqlCapabilities.php
│   │   │   ├── MySqlStreamConfigurator.php
│   │   │   └── MySqlBulkUpdateStrategy.php
│   │   └── PostgreSql/
│   │       ├── PostgreSqlDialect.php
│   │       ├── PostgreSqlCapabilities.php
│   │       ├── PostgreSqlStreamConfigurator.php
│   │       └── PostgreSqlBulkUpdateStrategy.php
│   ├── Query/
│   │   ├── Database.php
│   │   ├── PdoQueryExecutor.php
│   │   ├── Query.php
│   │   ├── QueryResult.php
│   │   └── StatementResult.php
│   ├── Stream/
│   │   ├── Stream.php
│   │   ├── DatabaseResultStream.php
│   │   ├── Chunk.php
│   │   └── StreamState.php
│   ├── Pipeline/
│   │   ├── Pipeline.php
│   │   ├── Stage/
│   │   │   ├── MapStage.php
│   │   │   ├── FilterStage.php
│   │   │   ├── TapStage.php
│   │   │   ├── FlatMapStage.php
│   │   │   ├── ChunkStage.php
│   │   │   └── TakeStage.php
│   │   └── Sink/
│   │       ├── EachSink.php
│   │       ├── CollectSink.php
│   │       ├── ReduceSink.php
│   │       ├── BatchInsertSink.php
│   │       └── BatchUpdateSink.php
│   ├── Bulk/
│   │   ├── BulkWriter.php
│   │   ├── BulkInsert.php
│   │   ├── BulkUpdate.php
│   │   ├── BulkDelete.php
│   │   ├── BulkOptions.php
│   │   ├── TransactionMode.php
│   │   ├── RetryPolicy.php
│   │   ├── RetryDecider.php
│   │   ├── BackoffStrategy.php
│   │   └── Strategy/
│   │       └── BulkUpdateStrategy.php
│   ├── Transaction/
│   │   ├── TransactionManager.php
│   │   └── Transaction.php
│   ├── Observability/
│   │   ├── NullObserver.php
│   │   ├── CompositeObserver.php
│   │   └── Event/
│   │       ├── ConnectionOpened.php
│   │       ├── ConnectionReused.php
│   │       ├── ConnectionClosed.php
│   │       ├── QueryExecuted.php
│   │       ├── StreamStarted.php
│   │       ├── StreamFinished.php
│   │       ├── ChunkProcessed.php
│   │       └── RetryAttempted.php
│   └── Exception/
│       ├── MazarbulException.php
│       ├── ConnectionException.php
│       ├── QueryException.php
│       ├── StreamException.php
│       ├── PipelineException.php
│       ├── BulkException.php
│       ├── TransactionException.php
│       └── UnsupportedDriverException.php
└── tests/
    ├── Unit/
    │   ├── Connection/
    │   ├── Driver/
    │   ├── Stream/
    │   ├── Pipeline/
    │   ├── Bulk/
    │   └── Transaction/
    ├── Integration/
    │   ├── MySql/
    │   └── PostgreSql/
    ├── Contract/
    │   └── DriverContractTest.php
    ├── Support/
    │   ├── FakeConnection.php
    │   ├── SpyObserver.php
    │   └── DataGenerator.php
    └── Performance/
        └── StreamingMemoryTest.php
```

Do not create empty architectural layers without immediate responsibility. The tree above is the target shape; implementation may introduce components incrementally per milestone.

---

# 5. Namespace and Composer

All production classes must use:

```php
namespace EreborCodeForge\Mazarbul;
```

or subnamespaces under it.

Recommended Composer configuration:

```json
{
  "name": "ereborcodeforge/mazarbul",
  "description": "Stream-oriented database access for PHP with lazy reusable connections, pipelines, chunk processing and bulk writes.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^8.3",
    "ext-pdo": "*"
  },
  "autoload": {
    "psr-4": {
      "EreborCodeForge\\Mazarbul\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "EreborCodeForge\\Mazarbul\\Tests\\": "tests/"
    }
  },
  "require-dev": {
    "phpunit/phpunit": "^12.0",
    "phpstan/phpstan": "^2.0"
  },
  "scripts": {
    "test": "phpunit",
    "analyse": "phpstan analyse src tests",
    "check": [
      "@test",
      "@analyse"
    ]
  }
}
```

Use the newest compatible stable versions at implementation time, but do not add dependencies unless they materially simplify the package.

The runtime core should remain dependency-light.

---

# 6. Connection architecture

## 6.1 ConnectionManager is the reusable service

The manager may be registered as a singleton in an external DI container, but **Mazarbul itself must not implement a process-global static singleton**.

Example:

```php
$manager = new ConnectionManager($factory);

$manager->define(
    'default',
    new ConnectionConfig(
        dsn: 'mysql:host=localhost;dbname=app',
        username: 'app',
        password: 'secret',
    )
);
```

Calling `define()` MUST NOT open the physical database connection.

## 6.2 Lazy materialization

```php
$db = $manager->database('default');
```

must not connect yet.

The connection is materialized only when an operation requires I/O:

```php
$db->fetchOne('SELECT ...');
```

## 6.3 ManagedConnection

`ManagedConnection` should own reusable connection lifecycle metadata:

```text
configured
   ↓
not connected
   ↓ first I/O
connected
   ↓
reused
   ↓ health/lifetime policy
reconnect or close
```

It should track at least:

- created timestamp;
- last used timestamp;
- last health check timestamp;
- transaction state;
- active stream/cursor state where relevant;
- current physical connection generation/id.

## 6.4 Lifecycle policy

Provide configuration similar to:

```php
new LifecyclePolicy(
    lazy: true,
    idleTimeoutSeconds: 60,
    maxLifetimeSeconds: 600,
    healthCheckIntervalSeconds: 30,
);
```

Do not use aggressive `SELECT 1` before every query by default. Health checks should be policy-driven.

## 6.5 Worker lifecycle hooks

Expose explicit methods usable by MithrilPHP, RoadRunner, FrankenPHP, queue workers, or custom runtimes:

```php
$manager->onWorkerStart();
$manager->onRequestStart();
$manager->onRequestEnd();
$manager->onWorkerStop();
```

Minimum required `onRequestEnd()` behavior:

- rollback leaked/uncommitted transaction;
- close active cursors/streams owned by the request when possible;
- clear request-scoped state;
- keep healthy physical connection reusable;
- close connection when policy requires it.

Mazarbul must work without lifecycle hooks too; hooks are an optimization/safety integration.

## 6.6 Do not rely on `PDO::ATTR_PERSISTENT` as the architecture

Native PDO persistent connections may be optional and explicitly configurable, but the default reusable-connection behavior for long-running workers should come from keeping a managed PDO instance alive in the worker process.

---

# 7. Traditional database API

Provide a high-level `Database` object.

Required initial API:

```php
$db->execute(string $sql, array $params = []): int;
$db->fetchOne(string $sql, array $params = []): ?array;
$db->fetchAll(string $sql, array $params = []): array;
$db->scalar(string $sql, array $params = []): mixed;
$db->stream(string $sql, array $params = []): ResultStream;
$db->transaction(callable $callback): mixed;
$db->lastInsertId(?string $name = null): string|false;
```

Optional convenience aliases can be added only when they do not duplicate behavior ambiguously.

Prepared statements must be used for parameter values.

`executeRaw()` should not be the normal public API. If raw execution remains available, name and document it clearly.

---

# 8. Stream architecture

## 8.1 Separate DB stream from generic pipeline

Database query execution should create a `DatabaseResultStream` that implements `ResultStream` / `IteratorAggregate`.

Example:

```php
$stream = $db->stream(
    'SELECT id, email FROM users WHERE active = ?',
    [1]
);

foreach ($stream as $row) {
    // one row at a time
}
```

## 8.2 Resource cleanup

A DB stream must close its cursor when:

- iteration reaches the end;
- consumer breaks iteration early;
- an exception occurs;
- stream is explicitly closed.

Use `try/finally` around generator execution.

Provide:

```php
$stream->close();
```

when practical.

## 8.3 Single-pass semantics

Database cursor streams should be treated as single-pass.

Calling terminal operations multiple times on the same consumed stream must either:

- throw a clear `StreamConsumedException`, or
- be explicitly backed by a re-executable query source.

Do not silently return incomplete/empty data because a generator was already consumed.

## 8.4 Driver-aware streaming

Create driver capability definitions.

Example:

```php
final readonly class DriverCapabilities
{
    public function __construct(
        public bool $supportsUnbufferedReads,
        public bool $supportsServerSideCursor,
        public bool $supportsReturning,
        public int $maxBindParameters,
    ) {}
}
```

For MySQL, configure true unbuffered streaming where supported.

For PostgreSQL, implement the safest supported PDO strategy first. If true server-side cursor support requires explicit transaction/cursor SQL, introduce it behind the driver implementation rather than pretending standard PDO `fetch()` is always unbuffered.

Document driver limitations.

---

# 9. Pipeline architecture

Pipeline is a core Mazarbul primitive.

It must not be coupled specifically to SQL. It operates over `iterable` streams.

Conceptual flow:

```text
Source
  ↓
Pipeline
  ├── map
  ├── filter
  ├── tap
  ├── flatMap
  ├── take
  └── chunk
  ↓
Sink / terminal operation
  ├── each
  ├── collect
  ├── reduce
  ├── batchInsert
  └── batchUpdate
```

## 9.1 Public API target

Example:

```php
$db
    ->stream('SELECT * FROM users')
    ->pipeline()
    ->filter(fn(array $row) => $row['active'] === 1)
    ->map(fn(array $row) => [
        'id' => $row['id'],
        'email' => strtolower($row['email']),
    ])
    ->chunk(500)
    ->each(fn(array $chunk) => processChunk($chunk));
```

Or:

```php
$db
    ->stream('SELECT * FROM legacy_users')
    ->pipeline()
    ->map($transformUser)
    ->toBatchInsert(
        database: $target,
        table: 'users',
        columns: ['name', 'email'],
        chunkSize: 1000,
    );
```

## 9.2 Pipeline should remain lazy

Calling:

```php
$pipeline = $stream
    ->pipeline()
    ->map(...)
    ->filter(...);
```

must not consume data.

Consumption starts only on terminal operation:

```php
$pipeline->each(...);
$pipeline->collect();
$pipeline->reduce(...);
$pipeline->toBatchInsert(...);
```

## 9.3 Stages

Implement first-class stage objects internally.

Contract:

```php
interface PipelineStage
{
    public function apply(iterable $input): iterable;
}
```

Minimum v1 stages:

- `MapStage`;
- `FilterStage`;
- `TapStage`;
- `FlatMapStage`;
- `ChunkStage`;
- `TakeStage`.

Do not add dozens of collection helpers in v1.

## 9.4 Custom stages

Allow extension without modifying core:

```php
$pipeline->through(new NormalizeEmailStage());
```

## 9.5 Terminal operations / sinks

Minimum:

```php
$pipeline->each(callable $consumer): void;
$pipeline->collect(?int $limit = null): array;
$pipeline->first(): mixed;
$pipeline->reduce(callable $reducer, mixed $initial = null): mixed;
```

`collect()` is intentionally memory-consuming and must be documented as such.

## 9.6 Backpressure model

Pipeline execution is synchronous/pull-based in v1.

A generator only requests the next upstream item when the downstream consumer requests it.

This provides natural pull-based backpressure without implementing an async reactive engine.

Do not introduce async/event-loop dependencies in v1.

---

# 10. Chunk processing

Support chunks as a streaming transformation:

```php
$db
    ->stream('SELECT * FROM events')
    ->pipeline()
    ->chunk(500)
    ->each(function (array $chunk): void {
        // at most ~500 records retained by this stage
    });
```

Requirements:

- `chunkSize >= 1`;
- final partial chunk must be emitted;
- source must not be materialized first;
- chunk operation must accept any iterable source;
- preserve item ordering;
- define whether keys are preserved; recommended default: normalize chunk keys to `0..n-1`.

---

# 11. Bulk operations

Evolve current `BulkExecutor` into a `BulkWriter` plus operation/strategy objects.

Required public API:

```php
$db->bulk()->insert(...);
$db->bulk()->update(...);
$db->bulk()->delete(...);
```

Example:

```php
$count = $db->bulk()->insert(
    table: 'users',
    columns: ['name', 'email'],
    rows: generateUsers(),
    options: new BulkOptions(chunkSize: 1000),
);
```

## 11.1 BulkOptions

Replace parameter-heavy method signatures with an immutable options value object where it improves clarity.

Example:

```php
new BulkOptions(
    chunkSize: 1000,
    transactionMode: TransactionMode::PER_CHUNK,
    retryPolicy: RetryPolicy::transient(maxAttempts: 3),
);
```

## 11.2 Transaction modes

Preserve current behavior conceptually:

```php
enum TransactionMode
{
    case NONE;
    case PER_CHUNK;
    case ALL;
}
```

Semantics must be documented and tested.

### NONE

- no automatic transaction;
- best throughput in some scenarios;
- partial success possible;
- retry safety must be explicit.

### PER_CHUNK

- one transaction per chunk;
- successful chunks remain committed;
- failed chunk can rollback/retry safely when operation is deterministic.

### ALL

- one transaction for the entire bulk operation;
- full rollback on failure;
- may create long transactions and locks for very large datasets.

## 11.3 Bulk INSERT

Must consume `iterable` incrementally.

Must account for driver max bind parameter limits.

Effective chunk size may be reduced automatically:

```text
maxRows = floor(maxBindParameters / numberOfColumns)
```

The caller's chunk size is an upper bound, not permission to exceed driver/server limits.

## 11.4 Bulk UPDATE

The current CASE/WHEN strategy is useful, but move SQL generation behind `BulkUpdateStrategy`.

Do not force one SQL shape on all drivers.

Potential strategies:

### MySQL

- CASE/WHEN for generic batch update;
- optional `INSERT ... ON DUPLICATE KEY UPDATE` for upsert-specific use cases later.

### PostgreSQL

- `UPDATE ... FROM (VALUES ...)` strategy;
- optional COPY/temp table optimization later.

## 11.5 Bulk DELETE

Chunk identifiers and generate bind placeholders safely.

Identifiers must pass dialect validation/quoting.

---

# 12. Retry architecture

Preserve and improve the existing retry concept.

Recommended model:

```php
final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts,
        public BackoffStrategy $backoff,
        public RetryDecider $decider,
    ) {}
}
```

`RetryDecider` should classify failures based on driver SQLSTATE/error code.

Examples of potentially retryable classes:

- deadlock;
- serialization failure;
- transient connection reset;
- temporary lock timeout, depending on driver/config.

Do not retry by default:

- syntax errors;
- constraint violations;
- authentication errors;
- invalid schema/table;
- programming errors.

Backoff implementations:

- none;
- fixed;
- exponential;
- exponential with jitter (recommended for real deployments).

Testing must not sleep in real time. Inject a sleeper/clock abstraction or make backoff testable without wall-clock delays.

Preserve deterministic fault injection in test support, not production public constructors unless there is a clear extension hook.

---

# 13. Transactions

Provide an explicit transaction API:

```php
$result = $db->transaction(function (Database $db) {
    $db->execute(...);
    return $db->fetchOne(...);
});
```

Requirements:

- commit on success;
- rollback on exception;
- rethrow original exception or wrap while preserving previous exception;
- transaction state visible to lifecycle cleanup;
- no leaked transaction after request/job completion.

Nested transaction behavior must be deliberate.

For v1 choose one:

1. reject nested transactions clearly; or
2. implement savepoints where driver supports them.

Recommended MVP: reject nested transactions with a clear exception; add savepoints later.

---

# 14. Driver and dialect architecture

Preserve the existing dialect concept but rename it under Mazarbul contracts.

```php
interface Dialect
{
    public function quoteIdentifier(string $identifier): string;
}
```

Driver-specific logic should include more than quoting.

```text
Driver
├── Dialect
├── Capabilities
├── Stream configuration
├── Error classification
└── Bulk strategies
```

`DriverResolver` must resolve explicitly from DSN/config.

Supported v1:

- `mysql`;
- `pgsql`.

Unsupported driver:

```php
throw new UnsupportedDriverException(...);
```

No fallback.

---

# 15. Observability hooks

Mazarbul should not depend directly on Datadog, OpenTelemetry, Prometheus, Monolog, or a specific logger.

Expose low-cost observer hooks/events.

Example contract:

```php
interface Observer
{
    public function notify(object $event): void;
}
```

Events should contain structured metadata and must never include passwords.

Useful events:

- connection opened;
- connection reused;
- connection closed;
- reconnect;
- query executed;
- query failed;
- stream started/finished;
- rows streamed;
- chunk processed;
- bulk operation started/finished;
- retry attempted;
- transaction started/committed/rolled back.

Query parameter values should not be emitted by default because they may contain PII/secrets.

Expose duration, row count, connection name, driver, operation type, attempt, and chunk index when available.

---

# 16. Error model

Create one root marker interface or abstract exception family.

Example:

```php
interface MazarbulException extends Throwable {}
```

Concrete exceptions may extend `RuntimeException` / `InvalidArgumentException` while implementing marker interface.

Do not lose original PDO exception information.

When wrapping:

```php
throw new QueryException(
    message: 'Query execution failed',
    previous: $e,
);
```

Do not include credentials in messages.

---

# 17. Public API examples

## 17.1 Setup

```php
use EreborCodeForge\Mazarbul\Connection\ConnectionConfig;
use EreborCodeForge\Mazarbul\Connection\ConnectionManager;
use EreborCodeForge\Mazarbul\Connection\PdoConnectionFactory;

$connections = new ConnectionManager(new PdoConnectionFactory());

$connections->define('default', new ConnectionConfig(
    dsn: $_ENV['DB_DSN'],
    username: $_ENV['DB_USER'],
    password: $_ENV['DB_PASSWORD'],
));

$db = $connections->database('default');
```

No connection should exist before the first actual I/O operation.

## 17.2 Traditional query

```php
$user = $db->fetchOne(
    'SELECT * FROM users WHERE id = ?',
    [$id],
);
```

## 17.3 Traditional collection

```php
$users = $db->fetchAll(
    'SELECT * FROM users WHERE status = ?',
    ['active'],
);
```

## 17.4 Streaming

```php
foreach ($db->stream('SELECT * FROM logs') as $row) {
    process($row);
}
```

## 17.5 Pipeline

```php
$db
    ->stream('SELECT id, email FROM users')
    ->pipeline()
    ->filter(static fn(array $row): bool => $row['email'] !== null)
    ->map(static fn(array $row): array => [
        ...$row,
        'email' => strtolower($row['email']),
    ])
    ->each($consumer);
```

## 17.6 Chunked pipeline

```php
$db
    ->stream('SELECT * FROM audit_events')
    ->pipeline()
    ->chunk(1000)
    ->each($processChunk);
```

## 17.7 Database-to-database pipeline

```php
$source
    ->stream('SELECT name, email FROM legacy_users')
    ->pipeline()
    ->map($normalize)
    ->toBatchInsert(
        database: $target,
        table: 'users',
        columns: ['name', 'email'],
        chunkSize: 1000,
    );
```

## 17.8 Bulk update

```php
$updated = $db->bulk()->update(
    table: 'users',
    keyColumn: 'id',
    updateColumns: ['status', 'updated_at'],
    rows: $rowsGenerator,
    options: new BulkOptions(
        chunkSize: 500,
        transactionMode: TransactionMode::PER_CHUNK,
    ),
);
```

---

# 18. Automated testing requirements

Automated testing is mandatory.

The agent must implement tests together with each feature, not after the complete implementation.

## 18.1 Unit tests

Must not require a real database where the behavior can be isolated.

Test at least:

### Connection

- defining connection does not connect;
- first I/O opens exactly one connection;
- subsequent I/O reuses connection;
- idle timeout closes/reopens;
- max lifetime rotates connection;
- failed connection can reconnect when policy allows;
- unknown connection throws;
- duplicate definition behavior is explicit;
- request-end rolls back leaked transaction;
- request-end closes active resources when possible.

### Dialects

- valid identifier quoting;
- invalid identifier rejected;
- MySQL quotes with backticks;
- PostgreSQL quotes with double quotes;
- unsupported driver rejected.

### Stream

- lazy source not invoked until iteration;
- row order preserved;
- cursor closes on normal completion;
- cursor closes on early termination;
- cursor closes after exception;
- consumed stream semantics are deterministic.

### Pipeline

- map is lazy;
- filter is lazy;
- map + filter compose correctly;
- flatMap works;
- tap does not alter items;
- take stops upstream consumption early;
- chunk emits complete and partial chunks;
- custom stage works;
- pipeline does not materialize data implicitly;
- terminal methods consume only once where appropriate.

### Bulk

- insert chunks correctly;
- final partial chunk executes;
- update strategy receives correct rows;
- delete chunks IDs;
- invalid chunk size rejected;
- bind parameter limit reduces effective chunk;
- NONE/PER_CHUNK/ALL semantics;
- retry succeeds after transient fault;
- retry stops after max attempts;
- non-retryable error is not retried;
- rollback happens before retry;
- backoff strategy receives correct attempt number.

### Transaction

- commit on success;
- rollback on exception;
- return callback value;
- nested transaction behavior.

## 18.2 Integration tests

Use real MySQL and PostgreSQL in CI service containers or Docker Compose.

Integration tests must validate:

- connection/open/reuse;
- prepared statements;
- fetchOne/fetchAll/scalar;
- streaming correctness;
- early stream close;
- transactions;
- bulk insert;
- bulk update;
- bulk delete;
- driver-specific identifier quoting;
- driver-specific bulk strategy;
- retry classification where feasible.

Do not require remote external databases.

## 18.3 Performance/memory regression test

Create a dedicated test or benchmark that demonstrates that streamed consumption does not grow memory linearly with row count for drivers/configurations advertised as streaming.

Example acceptance shape:

```text
100k rows  -> memory remains bounded
500k rows  -> memory remains within a reasonable delta
```

Do not assert unrealistically exact MB values across platforms. Compare bounded growth or configurable thresholds.

## 18.4 Test data

Use generators for large synthetic datasets:

```php
function rows(int $count): Generator
{
    for ($i = 0; $i < $count; $i++) {
        yield [$i, "value-$i"];
    }
}
```

Never commit massive generated output `.txt` files as benchmark results.

Store only summarized benchmark reports when useful.

---

# 19. Static analysis and quality gates

Required CI checks:

```text
composer validate
php -l / syntax check
phpunit
phpstan
```

Target PHPStan level should be high (prefer level 8/9/max if practical).

Coding rules:

- `declare(strict_types=1);` in every PHP production/test file;
- PSR-4;
- PSR-12-compatible formatting;
- final classes by default unless extension is intentional;
- immutable/readonly value objects where suitable;
- no service locator globals;
- no static mutable connection state;
- constructor dependency injection;
- small methods;
- early returns;
- enums/value objects instead of stringly typed transaction modes;
- avoid inheritance unless it provides real substitutability;
- contracts only where multiple implementations/testing boundaries justify them.

Do not create an interface for every class mechanically.

---

# 20. Benchmark strategy

Move ad-hoc benchmark scripts out of `src/`.

Benchmarks are not production source code.

Create `benchmarks/` and document environment/config.

Compare at least:

### Reads

1. `fetchAll()` buffered;
2. line-by-line stream;
3. stream + transformation;
4. stream + chunk(1000).

Metrics:

- elapsed time;
- peak memory;
- rows/sec.

### Writes

1. row-by-row prepared insert;
2. batch insert 100;
3. batch insert 500;
4. batch insert 1000;
5. batch insert effective driver limit.

Metrics:

- elapsed time;
- rows/sec;
- memory;
- query count.

### Bulk transaction modes

Compare:

- NONE;
- PER_CHUNK;
- ALL.

Do not use benchmark results as unit-test expectations.

---

# 21. Migration map from current source

Use the existing code as input according to this map.

```text
CURRENT                              TARGET
-----------------------------------------------------------------------
DatabaseConnectionConfig.php       -> Connection/ConnectionConfig.php
DatabaseConnectionManager.php      -> Connection/ConnectionManager.php
                                       + ManagedConnection.php
                                       + PdoConnectionFactory.php

DatabaseStream.php                 -> Query/Database.php
                                       + Stream/DatabaseResultStream.php
                                       + Pipeline/Pipeline.php
                                       + Pipeline/Stage/*
                                       + Pipeline/Sink/*

BulkExecutor.php                   -> Bulk/BulkWriter.php
                                       + Bulk/BulkInsert.php
                                       + Bulk/BulkUpdate.php
                                       + Bulk/BulkDelete.php
                                       + driver strategies

BulkRetryPolicy.php                -> Bulk/RetryPolicy.php
                                       + RetryDecider.php
                                       + BackoffStrategy.php

BulkTransactionMode.php            -> Bulk/TransactionMode.php

Dialect/SqlDialect.php             -> Contract/Dialect.php
Dialect/MySqlDialect.php           -> Driver/MySql/MySqlDialect.php
Dialect/PostgresDialect.php        -> Driver/PostgreSql/PostgreSqlDialect.php
Dialect/SqlDialectFactory.php      -> Driver/DriverResolver.php

Exception/*                        -> Exception/* with proper namespace

benchmark*.php                     -> benchmarks/*
benchmark*.txt                     -> remove generated data artifacts
```

Do not perform a blind file rename. Extract tested behavior and reimplement behind the new boundaries.

---

# 22. Implementation milestones

## Milestone 0 — Security and repository foundation

Deliver:

- remove secrets;
- document credential rotation requirement;
- Composer package;
- root namespace;
- PHPUnit;
- PHPStan;
- CI;
- baseline README;
- move benchmarks out of `src`;
- delete generated benchmark dumps.

Acceptance:

```bash
composer install
composer test
composer analyse
```

must execute successfully on a clean checkout.

## Milestone 1 — Driver + lazy connection core

Deliver:

- `ConnectionConfig`;
- `ConnectionManager`;
- `PdoConnectionFactory`;
- `ManagedConnection`;
- MySQL/PostgreSQL driver resolver;
- dialects;
- lifecycle policy;
- connection unit tests;
- MySQL/PostgreSQL integration connectivity tests.

Acceptance:

- registering connection does not open PDO;
- first query opens it;
- next query reuses it;
- unsupported driver fails explicitly.

## Milestone 2 — Traditional query API

Deliver:

- `Database`;
- `QueryExecutor`;
- `execute`;
- `fetchOne`;
- `fetchAll`;
- `scalar`;
- `lastInsertId`;
- transaction callback API;
- tests.

## Milestone 3 — True streaming

Deliver:

- `ResultStream`;
- cursor cleanup;
- MySQL streaming configuration;
- PostgreSQL documented/implemented streaming strategy;
- single-pass semantics;
- memory benchmark;
- tests for early termination and exceptions.

## Milestone 4 — Pipeline core

Deliver:

- lazy `Pipeline`;
- stages: map/filter/tap/flatMap/take/chunk;
- custom `through()`;
- terminal operations: each/first/reduce/collect;
- tests proving laziness and bounded memory behavior.

## Milestone 5 — Bulk writer

Deliver:

- insert/update/delete;
- chunking;
- transaction modes;
- dialect strategies;
- max bind parameter handling;
- generator input;
- tests.

## Milestone 6 — Retry + resilience

Deliver:

- RetryPolicy;
- retry classifier;
- backoff strategies;
- testable clock/sleeper;
- fault injection in tests;
- tests for rollback/retry semantics.

## Milestone 7 — Pipeline sinks to database

Deliver:

- `toBatchInsert()`;
- `toBatchUpdate()`;
- source DB -> transform -> target DB use case;
- tests with generators and two connections.

## Milestone 8 — Worker lifecycle + observability

Deliver:

- request/worker lifecycle hooks;
- observer events;
- no hard dependency on telemetry vendor;
- lifecycle leak tests;
- example integration with a long-running loop.

---

# 23. Definition of Done

Mazarbul v1 is done when all conditions below are true.

### Architecture

- no static global PDO registry;
- connection definitions are lazy;
- database, stream, pipeline, and bulk responsibilities are separated;
- MySQL/PostgreSQL differences are behind driver implementations;
- no ORM coupling.

### Traditional access

- execute/fetchOne/fetchAll/scalar/transaction work reliably.

### Streaming

- stream API consumes incrementally;
- advertised drivers use appropriate unbuffered/cursor strategy;
- cursor resources are cleaned up;
- memory behavior is benchmarked and documented.

### Pipeline

- transformations are lazy;
- stages compose;
- custom stages supported;
- chunks do not load the whole source;
- terminal sinks work.

### Bulk

- inserts, updates, deletes accept generators;
- transaction modes work;
- driver parameter limits are respected;
- retry behavior is safe/documented.

### Lifecycle

- connection reuse works in long-running processes;
- leaked transactions are rolled back at lifecycle boundary;
- stale connections can reconnect according to policy.

### Quality

- unit tests;
- integration tests for MySQL and PostgreSQL;
- memory/performance benchmark;
- static analysis;
- CI;
- no hardcoded secrets;
- documentation/examples.

---

# 24. Explicit non-goals for v1

Do NOT implement unless required by a concrete use case during development:

- ORM;
- Active Record;
- entity hydration framework;
- migrations engine;
- schema builder;
- full SQL query builder;
- distributed transactions;
- async PHP/event-loop driver;
- connection pool across processes;
- Redis/messaging abstractions;
- transparent caching;
- Rx-style observable ecosystem;
- automatic relation loading.

These can be separate packages/features later.

---

# 25. Architectural rules for the implementation agent

1. Start from tests for each milestone.
2. Keep existing working algorithms where they remain valid, especially bulk chunking and generator-based transformations.
3. Do not preserve current class boundaries merely for backward compatibility; this prototype is being shaped into a new library.
4. Avoid god classes.
5. Do not use static mutable service state.
6. Do not open connections during configuration/bootstrap.
7. Never claim an operation is streaming without validating driver buffering behavior.
8. All bulk APIs must accept `iterable`.
9. Pipeline transformations must remain lazy.
10. Pipeline terminal operations are the consumption boundary.
11. Always close DB cursors in `finally` paths.
12. Always clean transaction state after failure.
13. Unknown driver = explicit exception.
14. Never default silently to MySQL.
15. Never place benchmark or example code under production `src/`.
16. Never commit credentials or large generated benchmark output.
17. Prefer driver capabilities/strategies over `if ($driver === ...)` scattered through domain code.
18. Prefer a small public API and richer internal composition.
19. Include PHPDoc generics (`@template`, `@implements`, etc.) where useful for PHPStan and pipeline typing.
20. Every new public behavior requires automated tests and README/docs example.

---

# 26. Recommended API direction after v1

Potential future fluent API, only after the core primitives are proven:

```php
$targetCount = $source
    ->stream('SELECT * FROM source_users')
    ->pipeline()
    ->filter($isValid)
    ->map($normalize)
    ->tap($metrics)
    ->toBatchInsert(
        database: $target,
        table: 'users',
        columns: ['name', 'email'],
        chunkSize: 1000,
    );
```

The important architectural direction is:

```text
Database Source
      ↓
ResultStream
      ↓
Pipeline
      ↓
Stages (lazy)
      ↓
Sink
      ↓
Database / consumer / collection
```

And independently:

```text
ConnectionManager (long-lived service)
      ↓
ManagedConnection
      ↓
lazy open → reuse → health policy → reconnect/close
```

This separation is the foundation of Mazarbul.

---

# 27. Final implementation intent

Mazarbul should not be built as "another PDO wrapper".

Its identity is the combination of:

```text
lazy reusable connections
          +
stream-oriented reads
          +
composable pull-based pipelines
          +
chunked/batched writes
          +
long-running worker lifecycle safety
```

The traditional API exists because not every query deserves a stream.

The stream/pipeline API exists because large datasets should not require collection-sized memory.

The lifecycle layer exists because long-running PHP applications have different correctness requirements from classic request-per-process execution.

The implementation should optimize for **predictable resource usage, explicit behavior, testability, and driver correctness** before adding convenience abstractions.

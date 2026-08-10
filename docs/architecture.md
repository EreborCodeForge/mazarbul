# Architecture

Mazarbul separates connection lifecycle, traditional queries, streaming reads, pipelines, and bulk writes.

```text
ConnectionManager
      ↓
ManagedConnection (lazy open → reuse → health/lifetime → reconnect/close)
      ↓
Database
 ├─ traditional execute/fetch*
 ├─ stream() → DatabaseResultStream → Pipeline → Stages → Sinks
 └─ bulk() → BulkWriter (insert/update/delete)
```

## Principles

- No global static PDO registry
- `define()` never opens a socket
- Pipelines are lazy until a terminal sink
- Bulk APIs accept `iterable` / generators
- Driver SQL lives behind dialect/strategy objects
- Nested transactions are rejected in v1

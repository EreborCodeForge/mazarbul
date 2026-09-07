# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-09-06

### Added

- `Observer::isNoop()` so hot paths skip allocating observability events
- PostgreSQL server-side cursors for `stream()` (`DECLARE` / `FETCH` / `CLOSE`)
- Small `PDOStatement` cache in `PdoQueryExecutor` (invalidated by connection generation)
- Internal fused pipeline path for consecutive `map` / `filter` / `tap` / `take`
- Performance analysis document (`docs/PERFORMANCE_ANALYSIS.md`)

### Changed

- `BulkInsert` builds `VALUES` from a single-row placeholder template
- `scalar()` returns the first assoc value without `array_values`
- Docs: streaming PG cursors; warn against pipeline double-chunk before bulk sinks
- Composer `branch-alias` for `dev-main` → `1.1.x-dev`

## [1.0.1] - 2026-08-10

### Fixed

- PostgreSQL bulk UPDATE type casts for bound parameters (`?::bigint` / `?::text`)

## [1.0.0] - 2026-08-10

### Added

- First opening of the Book of Mazarbul for PHP 8.5+
- Lazy `ConnectionManager` / `ManagedConnection` lifecycle for long-running workers
- Traditional query API (`execute`, `fetchOne`, `fetchAll`, `scalar`, `transaction`)
- Driver-aware streaming reads (MySQL unbuffered, PostgreSQL documented strategy)
- Lazy pull-based pipelines (`map`, `filter`, `tap`, `flatMap`, `chunk`, `take`, `through`)
- Bulk insert/update/delete over `iterable` with transaction modes and retry policies
- Observability hooks without vendor lock-in
- Unit, contract, integration, and streaming memory tests
- CI quality + integration jobs and tag-based release workflow

[1.1.0]: https://github.com/EreborCodeForge/mazarbul/releases/tag/v1.1.0
[1.0.1]: https://github.com/EreborCodeForge/mazarbul/releases/tag/v1.0.1
[1.0.0]: https://github.com/EreborCodeForge/mazarbul/releases/tag/v1.0.0

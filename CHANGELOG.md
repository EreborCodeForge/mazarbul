# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/EreborCodeForge/mazarbul/releases/tag/v1.0.0

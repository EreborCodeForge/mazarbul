# Benchmarks

Scripts live in [`benchmarks/`](../benchmarks/). They are not production code.

Load credentials from environment variables (see [`.env.example`](../.env.example)). Never hardcode secrets.

Typical comparisons:

1. `fetchAll()` vs streaming vs stream+chunk
2. Row-by-row insert vs bulk insert chunk sizes
3. Bulk transaction modes `NONE` / `PER_CHUNK` / `ALL`

Metrics: elapsed time, peak memory, rows/sec. Store only summarized reports, not huge dumps.

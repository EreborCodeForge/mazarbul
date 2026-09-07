<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline;

use Closure;
use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Contract\PipelineStage;
use EreborCodeForge\Mazarbul\Pipeline\Sink\CollectSink;
use EreborCodeForge\Mazarbul\Pipeline\Sink\EachSink;
use EreborCodeForge\Mazarbul\Pipeline\Sink\ReduceSink;
use EreborCodeForge\Mazarbul\Pipeline\Stage\ChunkStage;
use EreborCodeForge\Mazarbul\Pipeline\Stage\FilterStage;
use EreborCodeForge\Mazarbul\Pipeline\Stage\FlatMapStage;
use EreborCodeForge\Mazarbul\Pipeline\Stage\MapStage;
use EreborCodeForge\Mazarbul\Pipeline\Stage\TakeStage;
use EreborCodeForge\Mazarbul\Pipeline\Stage\TapStage;
use EreborCodeForge\Mazarbul\Query\Database;
use Generator;

/**
 * Lazy pull-based pipeline. Stages do not consume until a terminal sink runs.
 */
final class Pipeline
{
    /**
     * @param iterable<mixed> $source
     * @param list<PipelineStage> $stages
     */
    private function __construct(
        private readonly iterable $source,
        private readonly array $stages = [],
    ) {
    }

    /**
     * @param iterable<mixed> $source
     */
    public static function from(iterable $source): self
    {
        return new self($source);
    }

    /**
     * @param Closure(mixed): mixed $mapper
     */
    public function map(Closure $mapper): self
    {
        return $this->through(new MapStage($mapper));
    }

    /**
     * @param Closure(mixed): bool $predicate
     */
    public function filter(Closure $predicate): self
    {
        return $this->through(new FilterStage($predicate));
    }

    /**
     * @param Closure(mixed): void $callback
     */
    public function tap(Closure $callback): self
    {
        return $this->through(new TapStage($callback));
    }

    /**
     * @param Closure(mixed): iterable<mixed> $mapper
     */
    public function flatMap(Closure $mapper): self
    {
        return $this->through(new FlatMapStage($mapper));
    }

    public function chunk(int $size): self
    {
        return $this->through(new ChunkStage($size));
    }

    public function take(int $limit): self
    {
        return $this->through(new TakeStage($limit));
    }

    public function through(PipelineStage $stage): self
    {
        $stages = $this->stages;
        $stages[] = $stage;

        return new self($this->source, $stages);
    }

    /**
     * @param Closure(mixed): void $consumer
     */
    public function each(Closure $consumer): void
    {
        (new EachSink($consumer))->consume($this->iterate());
    }

    /**
     * @return list<mixed>
     */
    #[\NoDiscard]
    public function collect(?int $limit = null): array
    {
        /** @var list<mixed> $result */
        $result = (new CollectSink($limit))->consume($this->iterate());

        return $result;
    }

    #[\NoDiscard]
    public function first(): mixed
    {
        foreach ($this->iterate() as $item) {
            return $item;
        }

        return null;
    }

    /**
     * @param Closure(mixed, mixed): mixed $reducer
     */
    #[\NoDiscard]
    public function reduce(Closure $reducer, mixed $initial = null): mixed
    {
        return (new ReduceSink($reducer, $initial))->consume($this->iterate());
    }

    /**
     * @param list<string> $columns
     */
    #[\NoDiscard]
    public function toBatchInsert(
        Database $database,
        string $table,
        array $columns,
        int $chunkSize = 1000,
        ?BulkOptions $options = null,
    ): int {
        $options ??= new BulkOptions(chunkSize: $chunkSize);

        /** @var iterable<array<int|string, mixed>> $rows */
        $rows = $this->iterate();

        return $database->bulk()->insert(
            table: $table,
            columns: $columns,
            rows: $rows,
            options: $options,
        );
    }

    /**
     * @param list<string> $updateColumns
     */
    #[\NoDiscard]
    public function toBatchUpdate(
        Database $database,
        string $table,
        string $keyColumn,
        array $updateColumns,
        int $chunkSize = 500,
        ?BulkOptions $options = null,
    ): int {
        $options ??= new BulkOptions(chunkSize: $chunkSize);

        /** @var iterable<array<string, mixed>> $rows */
        $rows = $this->iterate();

        return $database->bulk()->update(
            table: $table,
            keyColumn: $keyColumn,
            updateColumns: $updateColumns,
            rows: $rows,
            options: $options,
        );
    }

    /**
     * @return iterable<mixed>
     */
    private function iterate(): iterable
    {
        if ($this->canFuse()) {
            return $this->fusedIterate();
        }

        $iterable = $this->source;
        foreach ($this->stages as $stage) {
            $iterable = $stage->apply($iterable);
        }

        return $iterable;
    }

    private function canFuse(): bool
    {
        if ($this->stages === []) {
            return false;
        }

        foreach ($this->stages as $stage) {
            if (
                !$stage instanceof MapStage
                && !$stage instanceof FilterStage
                && !$stage instanceof TapStage
                && !$stage instanceof TakeStage
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Single-pass path for map/filter/tap/take — preserves stage order (including take).
     *
     * @return Generator<int, mixed>
     */
    private function fusedIterate(): Generator
    {
        yield from $this->fuseFrom($this->source, 0);
    }

    /**
     * @param iterable<mixed> $input
     *
     * @return Generator<int, mixed>
     */
    private function fuseFrom(iterable $input, int $start): Generator
    {
        $stages = $this->stages;
        $n = count($stages);
        if ($start >= $n) {
            foreach ($input as $item) {
                yield $item;
            }

            return;
        }

        $takeAt = null;
        for ($i = $start; $i < $n; ++$i) {
            if ($stages[$i] instanceof TakeStage) {
                $takeAt = $i;
                break;
            }
        }

        if ($takeAt === null) {
            foreach ($input as $item) {
                $current = $item;
                $drop = false;
                for ($i = $start; $i < $n; ++$i) {
                    $stage = $stages[$i];
                    if ($stage instanceof FilterStage) {
                        if (!($stage->predicate())($current)) {
                            $drop = true;
                            break;
                        }
                    } elseif ($stage instanceof TapStage) {
                        ($stage->callback())($current);
                    } elseif ($stage instanceof MapStage) {
                        $current = ($stage->mapper())($current);
                    }
                }
                if (!$drop) {
                    yield $current;
                }
            }

            return;
        }

        /** @var TakeStage $takeStage */
        $takeStage = $stages[$takeAt];
        $limit = $takeStage->limit();
        if ($limit === 0) {
            return;
        }

        $taken = 0;
        foreach ($input as $item) {
            $current = $item;
            $drop = false;
            for ($i = $start; $i < $takeAt; ++$i) {
                $stage = $stages[$i];
                if ($stage instanceof FilterStage) {
                    if (!($stage->predicate())($current)) {
                        $drop = true;
                        break;
                    }
                } elseif ($stage instanceof TapStage) {
                    ($stage->callback())($current);
                } elseif ($stage instanceof MapStage) {
                    $current = ($stage->mapper())($current);
                }
            }
            if ($drop) {
                continue;
            }

            yield from $this->fuseFrom([$current], $takeAt + 1);
            ++$taken;
            if ($taken >= $limit) {
                return;
            }
        }
    }
}

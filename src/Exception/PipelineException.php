<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

use InvalidArgumentException;

final class PipelineException extends InvalidArgumentException implements MazarbulException
{
    public static function invalidChunkSize(int $chunkSize): self
    {
        return new self(sprintf('Chunk size must be >= 1, got %d.', $chunkSize));
    }

    public static function invalidTakeLimit(int $limit): self
    {
        return new self(sprintf('Take limit must be >= 0, got %d.', $limit));
    }
}

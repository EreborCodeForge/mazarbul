<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Exception;

final class StreamConsumedException extends StreamException
{
    public static function create(): self
    {
        return new self('This stream has already been consumed and cannot be iterated again.');
    }
}

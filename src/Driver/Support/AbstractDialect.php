<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\Support;

use EreborCodeForge\Mazarbul\Contract\Dialect;
use InvalidArgumentException;

abstract class AbstractDialect implements Dialect
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    abstract protected function quoteChar(): string;

    public function assertValidIdentifier(string $identifier): void
    {
        if ($identifier === '' || preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid SQL identifier "%s".',
                $identifier,
            ));
        }
    }

    public function quoteIdentifier(string $identifier): string
    {
        $this->assertValidIdentifier($identifier);
        $quote = $this->quoteChar();

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use PDOException;
use Throwable;

final class RetryDecider
{
    /** @var list<string> */
    private const RETRYABLE_SQLSTATES = [
        '40001', // serialization failure
        '40P01', // deadlock detected (pgsql)
        '08000',
        '08001',
        '08003',
        '08006',
        '08007',
        '08S01',
        'HY000', // often used for gone away / lock wait depending on driver
    ];

    /** @var list<string> */
    private const RETRYABLE_MESSAGE_FRAGMENTS = [
        'deadlock',
        'lock wait timeout',
        'try restarting transaction',
        'server has gone away',
        'connection reset',
        'could not serialize',
    ];

    public function isRetryable(Throwable $error): bool
    {
        $current = $error;
        while ($current !== null) {
            if ($current instanceof PDOException) {
                $code = $current->errorInfo[0] ?? $current->getCode();
                $sqlState = is_string($code) || is_int($code) ? (string) $code : '';
                if ($sqlState !== '' && in_array($sqlState, self::RETRYABLE_SQLSTATES, true)) {
                    return true;
                }

                $message = strtolower($current->getMessage());
                foreach (self::RETRYABLE_MESSAGE_FRAGMENTS as $fragment) {
                    if (str_contains($message, $fragment)) {
                        return true;
                    }
                }
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}

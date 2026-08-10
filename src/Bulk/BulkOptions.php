<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use EreborCodeForge\Mazarbul\Exception\BulkException;

final readonly class BulkOptions
{
    public function __construct(
        public int $chunkSize = 1000,
        public TransactionMode $transactionMode = TransactionMode::PER_CHUNK,
        public RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {
        if ($this->chunkSize < 1) {
            throw BulkException::invalidChunkSize($this->chunkSize);
        }
    }

    public function withChunkSize(int $chunkSize): self
    {
        return clone($this, ['chunkSize' => $chunkSize]);
    }

    public function withTransactionMode(TransactionMode $mode): self
    {
        return clone($this, ['transactionMode' => $mode]);
    }

    public function withRetryPolicy(RetryPolicy $retryPolicy): self
    {
        return clone($this, ['retryPolicy' => $retryPolicy]);
    }
}

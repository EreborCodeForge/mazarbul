<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

enum TransactionMode
{
    case NONE;
    case PER_CHUNK;
    case ALL;
}

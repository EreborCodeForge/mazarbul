<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Stream;

enum StreamState
{
    case Ready;
    case Open;
    case Consumed;
    case Closed;
}

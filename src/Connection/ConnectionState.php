<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

enum ConnectionState
{
    case Configured;
    case NotConnected;
    case Connected;
    case Closed;
}

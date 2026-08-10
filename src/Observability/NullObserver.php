<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability;

use EreborCodeForge\Mazarbul\Contract\Observer;

final class NullObserver implements Observer
{
    public function notify(object $event): void
    {
        // intentionally empty
    }
}

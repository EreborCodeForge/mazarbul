<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

interface Observer
{
    public function notify(object $event): void;
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Contract;

interface ConnectionLifecycle
{
    public function onWorkerStart(): void;

    public function onRequestStart(): void;

    public function onRequestEnd(): void;

    public function onWorkerStop(): void;
}

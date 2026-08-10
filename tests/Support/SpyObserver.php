<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Support;

use EreborCodeForge\Mazarbul\Contract\Observer;

final class SpyObserver implements Observer
{
    /** @var list<object> */
    public private(set) array $events = [];

    public function notify(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param class-string $class
     *
     * @return list<object>
     */
    public function ofType(string $class): array
    {
        $matched = [];
        foreach ($this->events as $event) {
            if ($event instanceof $class) {
                $matched[] = $event;
            }
        }

        return $matched;
    }

    /**
     * @param class-string $class
     */
    public function countOf(string $class): int
    {
        return count($this->ofType($class));
    }
}

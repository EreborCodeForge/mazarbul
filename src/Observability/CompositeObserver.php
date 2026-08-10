<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability;

use EreborCodeForge\Mazarbul\Contract\Observer;

final class CompositeObserver implements Observer
{
    /** @var list<Observer> */
    private array $observers;

    /**
     * @param list<Observer> $observers
     */
    public function __construct(array $observers)
    {
        $this->observers = array_values($observers);
    }

    public function notify(object $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->notify($event);
        }
    }
}

<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability;

use EreborCodeForge\Mazarbul\Contract\Observer;

final class CompositeObserver implements Observer
{
    /** @var list<Observer> */
    private array $observers;

    private readonly bool $noop;

    /**
     * @param list<Observer> $observers
     */
    public function __construct(array $observers)
    {
        $this->observers = array_values($observers);
        $this->noop = array_all(
            $this->observers,
            static fn(Observer $observer): bool => $observer->isNoop(),
        );
    }

    public function notify(object $event): void
    {
        if ($this->noop) {
            return;
        }

        foreach ($this->observers as $observer) {
            $observer->notify($event);
        }
    }

    public function isNoop(): bool
    {
        return $this->noop;
    }
}

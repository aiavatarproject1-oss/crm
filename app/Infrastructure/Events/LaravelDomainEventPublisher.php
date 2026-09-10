<?php

namespace App\Infrastructure\Events;

use App\Application\Contracts\DomainEventPublisherInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelDomainEventPublisher implements DomainEventPublisherInterface
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}

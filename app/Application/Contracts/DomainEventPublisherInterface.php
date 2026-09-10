<?php

namespace App\Application\Contracts;

interface DomainEventPublisherInterface
{
    public function publish(object $event): void;
}

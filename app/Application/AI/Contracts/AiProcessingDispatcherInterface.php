<?php

namespace App\Application\AI\Contracts;

use App\Domain\AI\Entities\AiProcessingTask;

interface AiProcessingDispatcherInterface
{
    public function dispatch(AiProcessingTask $task): void;
}

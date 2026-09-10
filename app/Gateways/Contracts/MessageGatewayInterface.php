<?php

namespace App\Gateways\Contracts;

use App\DTO\IncomingMessageDTO;

interface MessageGatewayInterface
{
    public function receive(array $payload): IncomingMessageDTO;
}

<?php

namespace App\Interfaces\Http\Middleware;

use App\Application\Observability\CorrelationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public function __construct(private CorrelationContext $correlation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $correlationId = is_string($incoming) && trim($incoming) !== ''
            ? trim($incoming)
            : bin2hex(random_bytes(16));

        $this->correlation->set($correlationId);
        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext(['correlation_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}

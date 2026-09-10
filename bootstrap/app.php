<?php

use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\CorrelationContext;
use App\Interfaces\Http\Middleware\AssignCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', [
            AssignCorrelationId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApplicationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $previous = $exception->getPrevious();
            $correlationId = $request->attributes->get('correlation_id');
            if (! is_string($correlationId) || $correlationId === '') {
                $correlationId = app(CorrelationContext::class)->get();
            }

            Log::error($exception->getMessage(), [
                'correlation_id' => $correlationId,
                'exception' => $exception::class,
                'previous' => $previous instanceof Throwable ? $previous::class : null,
                'previous_message' => $previous?->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'correlation_id' => $correlationId,
            ], 503);
        });
    })->create();

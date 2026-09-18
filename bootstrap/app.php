<?php

use App\Application\Admin\Exceptions\AdminAuthException;
use App\Application\Admin\Exceptions\AdminManagementException;
use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\CorrelationContext;
use App\Domain\Shared\Exceptions\DomainException;
use App\Interfaces\Http\Middleware\AssignCorrelationId;
use App\Interfaces\Http\Middleware\EnsureAdminPermission;
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
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:admin']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', [
            AssignCorrelationId::class,
        ]);
        $middleware->alias([
            'permission' => EnsureAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([AdminAuthException::class, AdminManagementException::class, DomainException::class]);

        $exceptions->render(function (AdminAuthException|AdminManagementException $exception, Request $request) {
            $status = $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 400;

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => $exception instanceof AdminAuthException ? 'auth_error' : 'management_error',
                'correlation_id' => $request->attributes->get('correlation_id'),
            ], $status);
        });

        $exceptions->render(function (DomainException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => 'domain_error',
                'correlation_id' => $request->attributes->get('correlation_id'),
            ], 422);
        });

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

<?php

use App\Domain\Admin\Permissions\Permission as P;
use App\Interfaces\Http\Controllers\Admin\AdminsController;
use App\Interfaces\Http\Controllers\Admin\AuditLogsController;
use App\Interfaces\Http\Controllers\Admin\AuthController;
use App\Interfaces\Http\Controllers\Admin\CharactersController;
use App\Interfaces\Http\Controllers\Admin\ConversationsController;
use App\Interfaces\Http\Controllers\Admin\DashboardController;
use App\Interfaces\Http\Controllers\Admin\PipelineEventsController;
use App\Interfaces\Http\Controllers\Admin\QualityChecksController;
use App\Interfaces\Http\Controllers\Admin\RolesController;
use App\Interfaces\Http\Middleware\AuthenticateAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel API  (prefix: /api/v1/admin)
|--------------------------------------------------------------------------
| Auth: Bearer token (Sanctum). Every route below /me is permission-gated via
| the `permission:` middleware; super admins bypass all checks.
*/

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

Route::middleware([AuthenticateAdmin::class])->group(function (): void {
    // Session
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('permission:'.P::DASHBOARD_VIEW);

    // Conversations (monitor)
    Route::middleware('permission:'.P::CONVERSATIONS_VIEW)->group(function (): void {
        Route::get('/conversations', [ConversationsController::class, 'index']);
        Route::get('/conversations/{id}', [ConversationsController::class, 'show']);
        Route::get('/conversations/{id}/messages', [ConversationsController::class, 'messages']);
    });

    // AI Characters (structured settings)
    Route::middleware('permission:'.P::CHARACTER_VIEW.'|'.P::CHARACTER_MANAGE)->group(function (): void {
        Route::get('/characters', [CharactersController::class, 'index']);
        Route::get('/characters/{id}', [CharactersController::class, 'show']);
        Route::get('/characters/{id}/prompt-preview', [CharactersController::class, 'promptPreview']);
    });
    Route::patch('/characters/{id}', [CharactersController::class, 'update'])->middleware('permission:'.P::CHARACTER_MANAGE);

    // Admins
    Route::get('/admins', [AdminsController::class, 'index'])->middleware('permission:'.P::ADMINS_VIEW.'|'.P::ADMINS_MANAGE);
    Route::get('/admins/{id}', [AdminsController::class, 'show'])->middleware('permission:'.P::ADMINS_VIEW.'|'.P::ADMINS_MANAGE);
    Route::middleware('permission:'.P::ADMINS_MANAGE)->group(function (): void {
        Route::post('/admins', [AdminsController::class, 'store']);
        Route::patch('/admins/{id}', [AdminsController::class, 'update']);
        Route::post('/admins/{id}/reset-password', [AdminsController::class, 'resetPassword']);
        Route::delete('/admins/{id}', [AdminsController::class, 'destroy']);
    });

    // Roles & permissions
    Route::get('/permissions', [RolesController::class, 'permissions']);
    Route::get('/roles', [RolesController::class, 'index'])->middleware('permission:'.P::ROLES_VIEW.'|'.P::ROLES_MANAGE.'|'.P::ADMINS_MANAGE);
    Route::get('/roles/{id}', [RolesController::class, 'show'])->middleware('permission:'.P::ROLES_VIEW.'|'.P::ROLES_MANAGE);
    Route::middleware('permission:'.P::ROLES_MANAGE)->group(function (): void {
        Route::post('/roles', [RolesController::class, 'store']);
        Route::patch('/roles/{id}', [RolesController::class, 'update']);
        Route::delete('/roles/{id}', [RolesController::class, 'destroy']);
    });

    // Quality Checker (scores + threshold)
    Route::middleware('permission:'.P::REVIEW_QUEUE_VIEW.'|'.P::AI_SETTINGS_VIEW.'|'.P::CHARACTER_VIEW)->group(function (): void {
        Route::get('/quality-checks', [QualityChecksController::class, 'index']);
        Route::get('/quality-checks/settings', [QualityChecksController::class, 'settings']);
    });
    Route::put('/quality-checks/threshold', [QualityChecksController::class, 'updateThreshold'])
        ->middleware('permission:'.P::REVIEW_QUEUE_MANAGE.'|'.P::AI_SETTINGS_MANAGE.'|'.P::CHARACTER_MANAGE);

    // Logs
    Route::get('/logs/audit', [AuditLogsController::class, 'index'])->middleware('permission:'.P::AUDIT_LOGS_VIEW);
    Route::get('/logs/pipeline', [PipelineEventsController::class, 'index'])->middleware('permission:'.P::PIPELINE_LOGS_VIEW);
    Route::get('/logs/pipeline/events', [PipelineEventsController::class, 'events'])->middleware('permission:'.P::PIPELINE_LOGS_VIEW);
});

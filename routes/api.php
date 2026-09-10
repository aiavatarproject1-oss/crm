<?php

use App\Interfaces\Http\Controllers\AdminTaskController;
use App\Interfaces\Http\Controllers\AiProcessingTaskController;
use App\Interfaces\Http\Controllers\ConversationMessageController;
use App\Interfaces\Http\Controllers\Dev\DevInfluencerController;
use App\Interfaces\Http\Controllers\Dev\DevKnowledgeController;
use App\Interfaces\Http\Controllers\Dev\DevMemoryExtractionController;
use App\Interfaces\Http\Controllers\Dev\DevRuleController;
use App\Interfaces\Http\Controllers\Dev\DevTenantController;
use App\Interfaces\Http\Controllers\HealthController;
use App\Interfaces\Http\Controllers\InboundMessageController;
use App\Interfaces\Http\Controllers\UserMemoryController;
use App\Interfaces\Http\Middleware\AuthenticateInboundRequest;
use App\Interfaces\Http\Middleware\EnsureInboundInfluencerAccess;
use App\Interfaces\Http\Middleware\EnsureNonProduction;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class);

Route::post('/v1/inbound/messages', [InboundMessageController::class, 'store'])
    ->middleware([
        AuthenticateInboundRequest::class,
        'throttle:inbound',
        EnsureInboundInfluencerAccess::class,
    ]);

Route::get('/v1/ai/tasks/{taskId}', [AiProcessingTaskController::class, 'show']);
Route::get('/v1/conversations/{id}/messages', [ConversationMessageController::class, 'index']);
Route::get('/v1/memory/users/{userId}', [UserMemoryController::class, 'index']);
Route::get('/v1/admin/tasks', [AdminTaskController::class, 'index']);

Route::middleware([EnsureNonProduction::class])->prefix('v1/dev')->group(function (): void {
    Route::post('/tenants', [DevTenantController::class, 'store']);
    Route::post('/influencers', [DevInfluencerController::class, 'store']);
    Route::post('/memory/extract/{messageBatchId}', [DevMemoryExtractionController::class, 'store']);
    Route::post('/knowledge/text', [DevKnowledgeController::class, 'storeText']);
    Route::get('/knowledge/search', [DevKnowledgeController::class, 'search']);
    Route::post('/rules', [DevRuleController::class, 'store']);
    Route::get('/rules', [DevRuleController::class, 'index']);
});

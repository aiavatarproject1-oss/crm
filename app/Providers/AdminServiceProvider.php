<?php

namespace App\Providers;

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Application\Admin\Contracts\AdminTokenIssuerInterface;
use App\Application\Admin\Contracts\AuditLogRepositoryInterface;
use App\Application\Admin\Contracts\ConversationReadModelInterface;
use App\Application\Admin\Contracts\PasswordHasherInterface;
use App\Application\Admin\Contracts\PipelineEventReadModelInterface;
use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Application\Admin\Services\AdminContext;
use App\Application\Admin\Services\AuditLogger;
use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Domain\Shared\Events\ConversationUpdated;
use App\Domain\Shared\Events\MessageCreated;
use App\Infrastructure\Broadcasting\Listeners\BroadcastDomainEvents;
use App\Infrastructure\Persistence\MongoDB\Documents\PersonalAccessTokenDocument;
use App\Infrastructure\Persistence\MongoDB\ReadModels\MongoConversationReadModel;
use App\Infrastructure\Persistence\MongoDB\ReadModels\MongoPipelineEventReadModel;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoAdminRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoAuditLogRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoCharacterSettingsRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoRoleRepository;
use App\Infrastructure\Security\BcryptPasswordHasher;
use App\Infrastructure\Security\SanctumAdminTokenIssuer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Admin panel wiring: auth (Sanctum on MongoDB), RBAC, audit, read models, broadcasting bridge.
 */
class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AdminContext::class);
        $this->app->singleton(AuditLogger::class);

        $this->app->bind(AdminRepositoryInterface::class, MongoAdminRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, MongoRoleRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, MongoAuditLogRepository::class);
        $this->app->bind(PasswordHasherInterface::class, BcryptPasswordHasher::class);
        $this->app->bind(AdminTokenIssuerInterface::class, SanctumAdminTokenIssuer::class);
        $this->app->bind(ConversationReadModelInterface::class, MongoConversationReadModel::class);
        $this->app->bind(PipelineEventReadModelInterface::class, MongoPipelineEventReadModel::class);
        $this->app->bind(CharacterSettingsRepositoryInterface::class, MongoCharacterSettingsRepository::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessTokenDocument::class);

        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(3)->by(($request->ip() ?? 'unknown').'|'.strtolower((string) $request->input('username'))));

        Event::listen(MessageCreated::class, [BroadcastDomainEvents::class, 'onMessageCreated']);
        Event::listen(ConversationUpdated::class, [BroadcastDomainEvents::class, 'onConversationUpdated']);
    }
}

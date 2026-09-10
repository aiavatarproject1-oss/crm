<?php

namespace App\Providers;

use App\Infrastructure\Persistence\MongoDB\Repositories\MongoMessageRepository;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\InfluencerRepositoryInterface;
use App\Repositories\Contracts\MemoryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\RuleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\MongoDB\MongoConversationRepository;
use App\Repositories\MongoDB\MongoInfluencerRepository;
// use App\Repositories\MongoDB\MongoMessageRepository;
use App\Repositories\MongoDB\MongoMemoryRepository;
use App\Repositories\MongoDB\MongoRuleRepository;
use App\Repositories\MongoDB\MongoUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** Register repository interface bindings. */
    public function register(): void
    {
        $this->app->bind(InfluencerRepositoryInterface::class, MongoInfluencerRepository::class);
        $this->app->bind(UserRepositoryInterface::class, MongoUserRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, MongoConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MongoMessageRepository::class);
        $this->app->bind(MemoryRepositoryInterface::class, MongoMemoryRepository::class);
        $this->app->bind(RuleRepositoryInterface::class, MongoRuleRepository::class);
    }
}

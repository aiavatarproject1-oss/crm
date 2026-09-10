<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('influencers', function (Blueprint $collection): void {
            $collection->unique('slug', 'influencers_slug_unique');
        });

        Schema::connection('mongodb')->table('users', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('email_1');
            $collection->unique(
                ['platform' => 1, 'platform_user_id' => 1, 'influencer_id' => 1],
                'users_platform_identity_unique',
            );
            $collection->index('influencer_id', 'users_influencer_id_index');
        });

        Schema::connection('mongodb')->table('conversations', function (Blueprint $collection): void {
            $collection->index('user_id', 'conversations_user_id_index');
            $collection->index('influencer_id', 'conversations_influencer_id_index');
            $collection->index('last_message_at', 'conversations_last_message_at_index');
        });

        Schema::connection('mongodb')->table('messages', function (Blueprint $collection): void {
            $collection->index(
                ['conversation_id' => 1, 'created_at' => 1],
                'messages_conversation_created_at_index',
            );
            $collection->index('created_at', 'messages_created_at_index');
        });

        Schema::connection('mongodb')->table('memories', function (Blueprint $collection): void {
            $collection->index(
                ['user_id' => 1, 'importance_score' => -1],
                'memories_user_importance_index',
            );
        });

        Schema::connection('mongodb')->table('rules', function (Blueprint $collection): void {
            $collection->index(
                ['enabled' => 1, 'priority' => -1],
                'rules_enabled_priority_index',
            );
        });

        Schema::connection('mongodb')->table('knowledge_documents', function (Blueprint $collection): void {
            $collection->index('influencer_id', 'knowledge_documents_influencer_id_index');
            $collection->index('vector_id', 'knowledge_documents_vector_id_index');
        });

        Schema::connection('mongodb')->table('quality_checks', function (Blueprint $collection): void {
            $collection->index('message_id', 'quality_checks_message_id_index');
        });

        Schema::connection('mongodb')->table('admin_tasks', function (Blueprint $collection): void {
            $collection->index('conversation_id', 'admin_tasks_conversation_id_index');
            $collection->index('user_id', 'admin_tasks_user_id_index');
        });

        Schema::connection('mongodb')->table('logs', function (Blueprint $collection): void {
            $collection->index(['service' => 1, 'created_at' => -1], 'logs_service_created_at_index');
        });
    }

    public function down(): void
    {
        $indexes = [
            'influencers' => ['influencers_slug_unique'],
            'users' => ['users_platform_identity_unique', 'users_influencer_id_index'],
            'conversations' => ['conversations_user_id_index', 'conversations_influencer_id_index', 'conversations_last_message_at_index'],
            'messages' => ['messages_conversation_created_at_index', 'messages_created_at_index'],
            'memories' => ['memories_user_importance_index'],
            'rules' => ['rules_enabled_priority_index'],
            'knowledge_documents' => ['knowledge_documents_influencer_id_index', 'knowledge_documents_vector_id_index'],
            'quality_checks' => ['quality_checks_message_id_index'],
            'admin_tasks' => ['admin_tasks_conversation_id_index', 'admin_tasks_user_id_index'],
            'logs' => ['logs_service_created_at_index'],
        ];

        foreach ($indexes as $collection => $names) {
            Schema::connection('mongodb')->table($collection, function (Blueprint $blueprint) use ($names): void {
                foreach ($names as $name) {
                    $blueprint->dropIndexIfExists($name);
                }
            });
        }
    }
};

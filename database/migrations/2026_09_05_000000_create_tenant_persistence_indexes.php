<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('users', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('users_platform_identity_unique');
            $collection->unique(['tenant_id' => 1, 'influencer_id' => 1, 'platform' => 1, 'platform_user_id' => 1], 'users_tenant_influencer_platform_identity_unique');
        });
        Schema::connection('mongodb')->table('conversations', function (Blueprint $collection): void {
            $collection->index(['tenant_id' => 1, 'influencer_id' => 1, 'user_id' => 1, 'last_activity_at' => -1], 'conversations_tenant_influencer_user_activity_index');
        });
        Schema::connection('mongodb')->table('messages', function (Blueprint $collection): void {
            $collection->unique(['tenant_id' => 1, 'influencer_id' => 1, 'platform' => 1, 'external_message_id' => 1], 'messages_tenant_influencer_platform_external_unique');
            $collection->index(['tenant_id' => 1, 'influencer_id' => 1, 'conversation_id' => 1, 'created_at' => -1], 'messages_tenant_influencer_conversation_created_index');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('users', fn (Blueprint $collection) => $collection->dropIndexIfExists('users_tenant_influencer_platform_identity_unique'));
        Schema::connection('mongodb')->table('conversations', fn (Blueprint $collection) => $collection->dropIndexIfExists('conversations_tenant_influencer_user_activity_index'));
        Schema::connection('mongodb')->table('messages', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('messages_tenant_influencer_platform_external_unique');
            $collection->dropIndexIfExists('messages_tenant_influencer_conversation_created_index');
        });
    }
};

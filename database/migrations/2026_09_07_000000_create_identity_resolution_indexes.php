<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Identity & conversation continuity indexes (Phase 13.1).
 *
 * Idempotent: dropIndexIfExists before create.
 * Safe to re-run on environments that already applied earlier tenant indexes.
 *
 * User uniqueness (tenant-safe external identity):
 *   tenant_id + platform + platform_user_id
 *
 * Conversation resolution support:
 *   tenant_id + influencer_id + user_id + platform + status + last_activity_at
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('users', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('users_tenant_influencer_platform_identity_unique');
            $collection->dropIndexIfExists('users_platform_identity_unique');
            $collection->dropIndexIfExists('users_tenant_platform_identity_unique');
            $collection->unique(
                ['tenant_id' => 1, 'platform' => 1, 'platform_user_id' => 1],
                'users_tenant_platform_identity_unique',
            );
        });

        Schema::connection('mongodb')->table('conversations', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('conversations_tenant_influencer_user_activity_index');
            $collection->dropIndexIfExists('conversations_tenant_influencer_user_platform_status_activity_index');
            $collection->index(
                [
                    'tenant_id' => 1,
                    'influencer_id' => 1,
                    'user_id' => 1,
                    'platform' => 1,
                    'status' => 1,
                    'last_activity_at' => -1,
                ],
                'conversations_tenant_influencer_user_platform_status_activity_index',
            );
        });

        Schema::connection('mongodb')->table('messages', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('messages_tenant_influencer_platform_external_unique');
            $collection->unique(
                ['tenant_id' => 1, 'influencer_id' => 1, 'platform' => 1, 'external_message_id' => 1],
                'messages_tenant_influencer_platform_external_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('users', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('users_tenant_platform_identity_unique');
        });
        Schema::connection('mongodb')->table('conversations', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('conversations_tenant_influencer_user_platform_status_activity_index');
        });
        Schema::connection('mongodb')->table('messages', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('messages_tenant_influencer_platform_external_unique');
        });
    }
};

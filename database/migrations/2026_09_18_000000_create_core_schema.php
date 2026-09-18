<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Consolidated MongoDB schema (Phase 0 — admin panel foundation).
 *
 * - Every collection gets a `deleted_at` index (soft deletes everywhere).
 * - Compound indexes mirror the actual repository query paths (tenant → influencer → …).
 * - Idempotent: safe to re-run (`migrate:fresh` drops everything first).
 */
return new class extends Migration
{
    /** @var array<string, list<array{0: 'index'|'unique'|'expire', 1: array<string, int>|string, 2?: string, 3?: array<string, mixed>}>> */
    private const SCHEMA = [
        // ── Identity / conversation ────────────────────────────────────────────
        'users' => [
            ['unique', ['tenant_id' => 1, 'platform' => 1, 'platform_user_id' => 1], 'users_tenant_platform_identity_unique', self::PARTIAL_ACTIVE],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1], 'users_tenant_influencer_index'],
            ['index', ['username' => 1], 'users_username_index'],
        ],
        'conversations' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'user_id' => 1, 'platform' => 1, 'status' => 1, 'last_activity_at' => -1], 'conversations_resolution_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'last_activity_at' => -1], 'conversations_scope_activity_index'],
            ['index', ['status' => 1, 'last_activity_at' => -1], 'conversations_status_activity_index'],
            ['index', ['user_id' => 1], 'conversations_user_index'],
        ],
        'messages' => [
            ['unique', ['tenant_id' => 1, 'influencer_id' => 1, 'platform' => 1, 'external_message_id' => 1], 'messages_external_identity_unique', self::PARTIAL_ACTIVE],
            ['index', ['conversation_id' => 1, 'created_at' => 1], 'messages_conversation_created_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'conversation_id' => 1, 'created_at' => -1], 'messages_scope_conversation_created_index'],
            ['index', ['batch_id' => 1, 'sender_type' => 1], 'messages_batch_sender_index'],
            ['index', ['created_at' => -1], 'messages_created_index'],
            ['index', ['sender_type' => 1, 'created_at' => -1], 'messages_sender_created_index'],
        ],
        'message_batches' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'conversation_id' => 1, 'created_at' => -1], 'message_batches_scope_conversation_created_index'],
            ['index', ['tenant_id' => 1, 'user_id' => 1, 'created_at' => -1], 'message_batches_user_created_index'],
        ],
        'quality_checks' => [
            ['index', ['created_at' => -1], 'quality_checks_created_index'],
            ['index', ['character_id' => 1, 'created_at' => -1], 'quality_checks_character_created_index'],
            ['index', ['approved' => 1, 'created_at' => -1], 'quality_checks_approved_created_index'],
            ['index', ['conversation_id' => 1, 'created_at' => -1], 'quality_checks_conversation_created_index'],
        ],
        'ai_processing_tasks' => [
            ['unique', ['tenant_id' => 1, 'influencer_id' => 1, 'message_batch_id' => 1], 'ai_tasks_batch_unique', self::PARTIAL_ACTIVE],
            ['index', ['status' => 1, 'started_at' => -1], 'ai_tasks_status_started_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'status' => 1, 'started_at' => -1], 'ai_tasks_scope_status_started_index'],
            ['index', ['conversation_id' => 1, 'started_at' => -1], 'ai_tasks_conversation_started_index'],
        ],
        'admin_tasks' => [
            ['unique', ['tenant_id' => 1, 'influencer_id' => 1, 'message_id' => 1], 'admin_tasks_message_unique', self::PARTIAL_ACTIVE],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'status' => 1, 'created_at' => -1], 'admin_tasks_scope_status_created_index'],
            ['index', ['conversation_id' => 1], 'admin_tasks_conversation_index'],
        ],

        // ── Memory / rules / persona ───────────────────────────────────────────
        'memories' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'user_id' => 1, 'importance_score' => -1], 'memories_scope_user_importance_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'user_id' => 1, 'status' => 1, 'type' => 1], 'memories_scope_user_status_type_index'],
        ],
        'rules' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'enabled' => 1, 'priority' => -1], 'rules_scope_enabled_priority_index'],
        ],
        'personas' => [
            ['unique', ['tenant_id' => 1, 'influencer_id' => 1], 'personas_scope_unique', self::PARTIAL_ACTIVE],
        ],
        'prompt_policies' => [
            ['index', ['tenant_id' => 1], 'prompt_policies_tenant_index'],
        ],

        // ── Knowledge / RAG ────────────────────────────────────────────────────
        'knowledge_sources' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'type' => 1, 'created_at' => -1], 'knowledge_sources_scope_type_created_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'checksum' => 1], 'knowledge_sources_scope_checksum_index'],
        ],
        'knowledge_documents' => [
            ['unique', ['tenant_id' => 1, 'influencer_id' => 1, 'document_id' => 1, 'version' => 1], 'knowledge_documents_version_unique', self::PARTIAL_ACTIVE],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'type' => 1, 'version' => -1], 'knowledge_documents_scope_type_version_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'status' => 1], 'knowledge_documents_scope_status_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'checksum' => 1], 'knowledge_documents_scope_checksum_index'],
        ],
        'knowledge_chunks' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'document_id' => 1, 'position' => 1], 'knowledge_chunks_scope_document_position_index'],
        ],
        'knowledge_vectors' => [
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'chunk_id' => 1], 'knowledge_vectors_scope_chunk_index'],
            ['index', ['tenant_id' => 1, 'influencer_id' => 1, 'embedding_model' => 1], 'knowledge_vectors_scope_model_index'],
            ['unique', ['tenant_id' => 1, 'influencer_id' => 1, 'chunk_id' => 1, 'embedding_model' => 1, 'content_hash' => 1], 'knowledge_vectors_idempotency_unique', self::PARTIAL_ACTIVE],
        ],

        // ── AI Characters (structured settings) ────────────────────────────────
        'characters' => [
            ['unique', ['tenant_id' => 1, 'character_id' => 1], 'characters_tenant_character_unique', self::PARTIAL_ACTIVE],
            ['unique', ['slug' => 1], 'characters_slug_unique', self::PARTIAL_ACTIVE],
            ['index', ['status' => 1, 'updated_at' => -1], 'characters_status_updated_index'],
            ['index', ['tenant_id' => 1, 'status' => 1], 'characters_tenant_status_index'],
        ],

        // ── Admin panel / RBAC / auth ──────────────────────────────────────────
        'admins' => [
            ['unique', ['username' => 1], 'admins_username_unique', self::PARTIAL_ACTIVE],
            ['index', ['email' => 1], 'admins_email_index'],
            ['index', ['status' => 1, 'created_at' => -1], 'admins_status_created_index'],
            ['index', ['role_ids' => 1], 'admins_role_ids_index'],
        ],
        'roles' => [
            ['unique', ['slug' => 1], 'roles_slug_unique', self::PARTIAL_ACTIVE],
        ],
        'personal_access_tokens' => [
            ['unique', ['token' => 1], 'pat_token_unique', self::PARTIAL_ACTIVE],
            ['index', ['tokenable_type' => 1, 'tokenable_id' => 1], 'pat_tokenable_index'],
            ['index', ['expires_at' => 1], 'pat_expires_index'],
        ],

        // ── Observability ──────────────────────────────────────────────────────
        'audit_logs' => [
            ['index', ['created_at' => -1], 'audit_logs_created_index'],
            ['index', ['actor_id' => 1, 'created_at' => -1], 'audit_logs_actor_created_index'],
            ['index', ['action' => 1, 'created_at' => -1], 'audit_logs_action_created_index'],
            ['index', ['target_type' => 1, 'target_id' => 1, 'created_at' => -1], 'audit_logs_target_created_index'],
        ],
        'pipeline_events' => [
            ['index', ['created_at' => -1], 'pipeline_events_created_index'],
            ['index', ['conversation_id' => 1, 'created_at' => -1], 'pipeline_events_conversation_created_index'],
            ['index', ['correlation_id' => 1], 'pipeline_events_correlation_index'],
            ['index', ['event' => 1, 'created_at' => -1], 'pipeline_events_event_created_index'],
            ['index', ['level' => 1, 'created_at' => -1], 'pipeline_events_level_created_index'],
        ],

        // ── Laravel runtime ────────────────────────────────────────────────────
        'failed_jobs' => [
            ['unique', ['uuid' => 1], 'failed_jobs_uuid_unique', self::PARTIAL_ACTIVE],
            ['index', ['failed_at' => -1], 'failed_jobs_failed_at_index'],
        ],
    ];

    /** Unique indexes ignore soft-deleted rows so identifiers can be reused after delete. */
    private const PARTIAL_ACTIVE = ['partialFilterExpression' => ['deleted_at' => null]];

    public function up(): void
    {
        $schema = Schema::connection('mongodb');

        foreach (self::SCHEMA as $collection => $indexes) {
            $apply = function (Blueprint $blueprint) use ($collection, $indexes): void {
                $blueprint->dropIndexIfExists($collection.'_deleted_at_index');
                $blueprint->index(['deleted_at' => 1], $collection.'_deleted_at_index');

                foreach ($indexes as $definition) {
                    [$kind, $columns] = $definition;
                    $name = $definition[2] ?? null;
                    $options = $definition[3] ?? [];

                    if ($name !== null) {
                        $blueprint->dropIndexIfExists($name);
                    }

                    match ($kind) {
                        'unique' => $blueprint->unique($columns, $name, null, $options),
                        'index' => $blueprint->index($columns, $name, null, $options),
                        'expire' => $blueprint->expire($columns, (int) ($options['seconds'] ?? 0)),
                    };
                }
            };

            if ($schema->hasTable($collection)) {
                $schema->table($collection, $apply);
            } else {
                $schema->create($collection, $apply);
            }
        }

        // Pipeline events are high-volume: expire after N days (default 30).
        $ttlDays = (int) config('observability.pipeline_events_ttl_days', 30);
        if ($ttlDays > 0) {
            $schema->table('pipeline_events', function (Blueprint $blueprint) use ($ttlDays): void {
                $blueprint->dropIndexIfExists('created_at_1');
                $blueprint->expire('created_at', $ttlDays * 86400);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('mongodb');

        foreach (array_keys(self::SCHEMA) as $collection) {
            if ($schema->hasTable($collection)) {
                $schema->drop($collection);
            }
        }
    }
};

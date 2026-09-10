<?php

namespace Tests\Unit\Infrastructure;

use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Quality\ValueObjects\AdminTaskId;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminTaskDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\AiProcessingTaskDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeChunkDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeDocumentDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeSourceDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\MemoryDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageBatchDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\PersonaDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\RuleDocument;
use App\Infrastructure\Persistence\MongoDB\Documents\VectorRecordDocument;
use Tests\TestCase;

final class MongoPersistenceConsistencyTest extends TestCase
{
    public function test_document_classes_use_table_not_collection(): void
    {
        $map = [
            PersonaDocument::class => 'personas',
            RuleDocument::class => 'rules',
            MemoryDocument::class => 'memories',
            KnowledgeDocumentDocument::class => 'knowledge_documents',
            KnowledgeChunkDocument::class => 'knowledge_chunks',
            KnowledgeSourceDocument::class => 'knowledge_sources',
            VectorRecordDocument::class => 'knowledge_vectors',
            AdminTaskDocument::class => 'admin_tasks',
            MessageBatchDocument::class => 'message_batches',
            AiProcessingTaskDocument::class => 'ai_processing_tasks',
        ];

        foreach ($map as $class => $expected) {
            self::assertSame($expected, (new $class)->getTable(), $class);
        }
    }

    public function test_persona_save_and_retrieve_preserves_domain_id(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $personaId = new PersonaId('persona-persist-'.$suffix);
        $tenant = new TenantId('tenant-persist-'.$suffix);
        $influencer = new InfluencerId('influencer-persist-'.$suffix);
        $persona = new Persona($personaId, $tenant, $influencer, 'Sofia', 'en', 'warm', 'friendly', 'desc', ['Be kind'], ['fixture' => true]);

        $repo = $this->app->make(PersonaRepositoryInterface::class);
        $repo->save($persona);
        $loaded = $repo->findByInfluencer($tenant, $influencer);

        self::assertNotNull($loaded);
        self::assertSame((string) $personaId, (string) $loaded->id());
        self::assertSame('personas', (new PersonaDocument)->getTable());
    }

    public function test_rule_save_and_lookup_finds_enabled_rule(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $tenant = new TenantId('tenant-demo');
        $influencer = new InfluencerId('influencer-sofia');
        $rule = new Rule(
            new RuleId('rule-ai-'.$suffix),
            $tenant,
            $influencer,
            'ai_keyword_'.$suffix,
            new RuleType(RuleType::KEYWORD),
            ['AI'],
            100,
            new RuleDecision(RuleDecision::ADMIN_REVIEW),
            true,
            1,
            ['fixture' => 'phase-13-2'],
        );

        $repo = $this->app->make(RuleRepositoryInterface::class);
        $repo->save($rule);
        $found = $repo->findEnabledRules($tenant, $influencer);

        self::assertTrue(collect($found)->contains(fn (Rule $item): bool => (string) $item->id() === (string) $rule->id()));
        self::assertSame('rules', (new RuleDocument)->getTable());
    }

    public function test_knowledge_document_save_preserves_domain_id_on_hydrate(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $tenant = new TenantId('tenant-persist-'.$suffix);
        $influencer = new InfluencerId('influencer-persist-'.$suffix);
        $documentId = new KnowledgeDocumentId('doc-'.$suffix);
        $document = new KnowledgeDocument($documentId, $tenant, $influencer, KnowledgeType::FAQ, 'FAQ', 'content', 1, 'active', 'test', []);

        $repo = $this->app->make(KnowledgeDocumentRepositoryInterface::class);
        $repo->save($document);
        $loaded = $repo->findVersion($tenant, $influencer, $documentId, 1);

        self::assertNotNull($loaded);
        self::assertSame((string) $documentId, (string) $loaded->id());
        self::assertSame('knowledge_documents', (new KnowledgeDocumentDocument)->getTable());
    }

    public function test_admin_task_save_preserves_domain_id(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $id = new AdminTaskId('admin-'.$suffix);
        $task = new AdminTask(
            $id,
            new TenantId('tenant-persist-'.$suffix),
            new InfluencerId('influencer-persist-'.$suffix),
            new ConversationId('conv-'.$suffix),
            new MessageId('msg-'.$suffix),
            'quality failed',
            'pending',
            ['score' => 0.1],
        );

        $repo = $this->app->make(AdminTaskRepositoryInterface::class);
        $repo->save($task);

        $stored = AdminTaskDocument::query()->find((string) $id);
        self::assertNotNull($stored);
        self::assertSame((string) $id, (string) $stored->getAttribute('_id'));
        self::assertSame('admin_tasks', (new AdminTaskDocument)->getTable());
    }

    public function test_persistence_index_migration_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_09_07_130000_create_persistence_consistency_indexes.php');
        $migration->up();
        $migration->up();

        self::assertTrue(true);
    }

    public function test_rule_lookup_is_tenant_isolated(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $repo = $this->app->make(RuleRepositoryInterface::class);
        $repo->save(new Rule(
            new RuleId('rule-a-'.$suffix),
            new TenantId('tenant-a-'.$suffix),
            new InfluencerId('influencer-sofia'),
            'iso-a',
            new RuleType(RuleType::KEYWORD),
            ['secret-a'],
            10,
            new RuleDecision(RuleDecision::BLOCK),
            true,
            1,
        ));
        $repo->save(new Rule(
            new RuleId('rule-b-'.$suffix),
            new TenantId('tenant-b-'.$suffix),
            new InfluencerId('influencer-sofia'),
            'iso-b',
            new RuleType(RuleType::KEYWORD),
            ['secret-b'],
            10,
            new RuleDecision(RuleDecision::BLOCK),
            true,
            1,
        ));

        $a = $repo->findEnabledRules(new TenantId('tenant-a-'.$suffix), new InfluencerId('influencer-sofia'));
        $b = $repo->findEnabledRules(new TenantId('tenant-b-'.$suffix), new InfluencerId('influencer-sofia'));

        self::assertCount(1, $a);
        self::assertCount(1, $b);
        self::assertSame('secret-a', $a[0]->patterns[0]);
        self::assertSame('secret-b', $b[0]->patterns[0]);
    }
}

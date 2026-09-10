// Local development fixtures for the Postman collection only.
// Keep these IDs synchronized with AI-Influencer-MVP.postman_environment.json.
const database = db.getSiblingDB('ai_influencer');
const tenantId = 'tenant-demo';
const influencerId = 'influencer-sofia';

database.personas.updateOne(
    { tenant_id: tenantId, influencer_id: influencerId },
    {
        $set: {
            name: 'Sofia',
            language: 'en',
            tone: 'warm',
            style: 'friendly',
            description: 'A thoughtful AI influencer focused on useful conversations.',
            system_rules: ['Be respectful', 'Do not invent facts', 'Use retrieved knowledge when available'],
            metadata: { fixture: 'postman' },
            updated_at: new Date(),
        },
        $setOnInsert: { _id: 'persona-sofia', created_at: new Date() },
    },
    { upsert: true },
);

database.rules.updateOne(
    { _id: 'rule-ai-identity' },
    {
        $set: {
            tenant_id: tenantId,
            influencer_id: influencerId,
            name: 'ai_identity_question',
            type: 'KEYWORD',
            patterns: ['Are you an AI?', 'هوش مصنوعی'],
            priority: 100,
            action: 'ADMIN_REVIEW',
            enabled: true,
            version: 1,
            metadata: { fixture: 'postman' },
            updated_at: new Date(),
        },
        $setOnInsert: { created_at: new Date() },
    },
    { upsert: true },
);

database.rules.updateOne(
    { _id: 'rule-spam-block' },
    {
        $set: {
            tenant_id: tenantId,
            influencer_id: influencerId,
            name: 'spam',
            type: 'KEYWORD',
            patterns: ['spam', 'buy now'],
            priority: 90,
            action: 'BLOCK',
            enabled: true,
            version: 1,
            metadata: { fixture: 'postman' },
            updated_at: new Date(),
        },
        $setOnInsert: { created_at: new Date() },
    },
    { upsert: true },
);

printjson({
    database: database.getName(),
    tenant_id: tenantId,
    influencer_id: influencerId,
    persona_ready: database.personas.countDocuments({ tenant_id: tenantId, influencer_id: influencerId }),
    enabled_rules: database.rules.countDocuments({ tenant_id: tenantId, influencer_id: influencerId, enabled: true }),
});

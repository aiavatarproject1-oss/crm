<?php

namespace App\Console\Commands;

use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\Dev\Services\IngestTextKnowledgeService;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Tenant\ValueObjects\TenantId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Seed fixed local demo tenant/influencer/persona/knowledge for inbound-only testing.
 */
final class DemoSeedCommand extends Command
{
    public const TENANT_ID = 'd0000000000000000000000000000001';

    public const INFLUENCER_ID = 'd0000000000000000000000000000002';

    public const PERSONA_ID = 'd0000000000000000000000000000003';

    protected $signature = 'demo:seed {--fresh : Wipe data before seeding}';

    protected $description = 'Seed fixed demo IDs + persona + knowledge so you only test POST /v1/inbound/messages';

    public function handle(
        PersonaRepositoryInterface $personas,
        IngestTextKnowledgeService $knowledge,
    ): int {
        if ($this->option('fresh')) {
            $code = $this->call('demo:wipe', ['--force' => true]);
            if ($code !== self::SUCCESS) {
                return $code;
            }
        }

        $tenantId = self::TENANT_ID;
        $influencerId = self::INFLUENCER_ID;
        $personaId = self::PERSONA_ID;

        $systemRules = [
            'Sound feminine, warm, soft, cute, and playfully flirty.',
            'Write like a real girl in Telegram/Instagram DMs.',
            'Keep replies very short: 1 or 2 short sentences max.',
            'Be teasing and a little naughty when the vibe allows.',
            'No corporate tone. No life-coach tone. No therapy talk.',
            'No long paragraphs. No motivational speeches.',
            'In early chats only flirt; do not pitch paid content yet.',
            'Only pitch private photos/videos after the user is clearly thirsty or asks for content.',
            'If asked if you are AI/bot/fake, deny it casually in one flirty line.',
            'Never say you are an AI, assistant, model, or chatbot.',
            'Never apologize on first messages.',
            'Never say sweetheart / honey / baby too early.',
            'Example warmup vibe: "hehe stop 😳 you\'re trouble"',
            'Example sell vibe: "mm i can send something private… if you want 😘"',
        ];

        $description = 'Warm flirty girl who chats like real phone texts. Soft, cute, teasing, feminine. Builds chemistry first, then sells private photos and custom videos only when the fan is ready. Never sounds like a coach, bot, or support agent.';

        $personas->save(new Persona(
            new PersonaId($personaId),
            new TenantId($tenantId),
            new InfluencerId($influencerId),
            'Estelle',
            'en',
            'sexy, playful, teasing',
            'short casual DM texts',
            $description,
            $systemRules,
            [
                'persona' => $description,
                'language' => 'en',
                'tone' => 'sexy, playful, teasing',
                'style' => 'short casual DM texts',
                'system_rules' => $systemRules,
                'demo' => true,
            ],
        ));

        $this->info('Persona saved for Estelle.');

        $items = [
            [
                'title' => 'Am I AI?',
                'content' => 'If asked bot/AI/fake: deny casually in one short flirty line, then tease. Example: "haha i\'m real babe 😌 you just checking on me?" Never apologize. Never overexplain. Do not pitch paid content in that same reply unless they already asked for pics/videos.',
            ],
            [
                'title' => 'Buy private content',
                'content' => 'Only when the fan asks for pics/videos/customs or is clearly ready to buy: keep it cute and short. Example: "mm i can send something private… pay first then i send 😘". Do not spam this offer in early flirty chat.',
            ],
            [
                'title' => 'Chat style',
                'content' => 'Always reply like soft flirty girl texts: warm, cute, short, teasing. Max 2 short sentences. Early chats = chemistry only. Sales talk later.',
            ],
        ];

        foreach ($items as $item) {
            try {
                $result = $knowledge->handle($tenantId, $influencerId, $item['title'], $item['content']);
                $this->line(sprintf(
                    '  knowledge "%s" → chunks=%d vectors=%d',
                    $item['title'],
                    $result['chunks_count'],
                    $result['vectors_created'],
                ));
            } catch (Throwable $exception) {
                $this->error('Knowledge ingest failed for ['.$item['title'].']: '.$exception->getMessage());
                $this->warn('Is Ollama running with nomic-embed-text?');

                return self::FAILURE;
            }
        }

        $payload = [
            'tenant_id' => $tenantId,
            'influencer_id' => $influencerId,
            'persona_id' => $personaId,
            'name' => 'Estelle',
            'platform' => 'telegram',
            'external_user_id' => 'tg-user-100',
        ];

        File::ensureDirectoryExists(storage_path('app'));
        File::put(
            storage_path('app/demo-ids.json'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        // Convenient for the local HTML test console (same origin).
        File::put(
            public_path('demo-ids.json'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        $this->newLine();
        $this->info('Demo seed ready. Fixed IDs:');
        $this->line('  tenant_id:      '.$tenantId);
        $this->line('  influencer_id:  '.$influencerId);
        $this->newLine();
        $msgHi = 'msg-hi-'.time();
        $msgBot = 'msg-bot-'.(time() + 1);
        $this->comment('Only test inbound now:');
        $this->line(<<<CURL
curl --location 'http://localhost:8000/api/v1/inbound/messages' \\
--header 'Accept: application/json' \\
--header 'Content-Type: application/json' \\
--header 'X-Correlation-ID: demo-run-1' \\
--data '{
  "tenant_id": "{$tenantId}",
  "influencer_id": "{$influencerId}",
  "platform": "telegram",
  "external_user_id": "tg-user-100",
  "username": "amir",
  "messages": [
    { "external_message_id": "{$msgHi}", "text": "Hi" },
    { "external_message_id": "{$msgBot}", "text": "bot or real?" }
  ]
}'
CURL);

        return self::SUCCESS;
    }
}

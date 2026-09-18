<?php

namespace App\Domain\Admin\Permissions;

/**
 * Canonical permission registry (code is the source of truth; roles reference these keys).
 *
 * Naming: `<module>.<action>`. Add new keys here when a new admin capability ships,
 * then the UI/role editor picks them up automatically via `Permission::all()`.
 */
final class Permission
{
    // Dashboard
    public const DASHBOARD_VIEW = 'dashboard.view';

    // Conversations
    public const CONVERSATIONS_VIEW = 'conversations.view';

    public const CONVERSATIONS_LIVE = 'conversations.live';

    public const CONVERSATIONS_REPLY = 'conversations.reply';

    public const CONVERSATIONS_HANDOFF_MANAGE = 'conversations.handoff.manage';

    // Character / persona / prompts
    public const CHARACTER_VIEW = 'character.view';

    public const CHARACTER_MANAGE = 'character.manage';

    public const PROMPTS_VIEW = 'prompts.view';

    public const PROMPTS_MANAGE = 'prompts.manage';

    public const KNOWLEDGE_VIEW = 'knowledge.view';

    public const KNOWLEDGE_MANAGE = 'knowledge.manage';

    public const RULES_VIEW = 'rules.view';

    public const RULES_MANAGE = 'rules.manage';

    // Quality / AI settings
    public const AI_SETTINGS_VIEW = 'ai_settings.view';

    public const AI_SETTINGS_MANAGE = 'ai_settings.manage';

    // Review queue (AI exposure attempts, low quality, handoffs)
    public const REVIEW_QUEUE_VIEW = 'review_queue.view';

    public const REVIEW_QUEUE_MANAGE = 'review_queue.manage';

    // Admin management / RBAC
    public const ADMINS_VIEW = 'admins.view';

    public const ADMINS_MANAGE = 'admins.manage';

    public const ROLES_VIEW = 'roles.view';

    public const ROLES_MANAGE = 'roles.manage';

    // Logs
    public const AUDIT_LOGS_VIEW = 'audit_logs.view';

    public const PIPELINE_LOGS_VIEW = 'pipeline_logs.view';

    // Tenant / platform settings
    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    /**
     * Grouped for the UI (module => permissions).
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'dashboard' => [self::DASHBOARD_VIEW],
            'conversations' => [
                self::CONVERSATIONS_VIEW,
                self::CONVERSATIONS_LIVE,
                self::CONVERSATIONS_REPLY,
                self::CONVERSATIONS_HANDOFF_MANAGE,
            ],
            'character' => [self::CHARACTER_VIEW, self::CHARACTER_MANAGE],
            'prompts' => [self::PROMPTS_VIEW, self::PROMPTS_MANAGE],
            'knowledge' => [self::KNOWLEDGE_VIEW, self::KNOWLEDGE_MANAGE],
            'rules' => [self::RULES_VIEW, self::RULES_MANAGE],
            'ai_settings' => [self::AI_SETTINGS_VIEW, self::AI_SETTINGS_MANAGE],
            'review_queue' => [self::REVIEW_QUEUE_VIEW, self::REVIEW_QUEUE_MANAGE],
            'admins' => [self::ADMINS_VIEW, self::ADMINS_MANAGE],
            'roles' => [self::ROLES_VIEW, self::ROLES_MANAGE],
            'logs' => [self::AUDIT_LOGS_VIEW, self::PIPELINE_LOGS_VIEW],
            'settings' => [self::SETTINGS_VIEW, self::SETTINGS_MANAGE],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_merge(...array_values(self::groups())));
    }

    public static function isValid(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public static function normalize(array $permissions): array
    {
        $clean = [];
        foreach ($permissions as $permission) {
            if (! is_string($permission)) {
                continue;
            }
            $permission = trim($permission);
            if (self::isValid($permission)) {
                $clean[$permission] = true;
            }
        }

        return array_keys($clean);
    }
}

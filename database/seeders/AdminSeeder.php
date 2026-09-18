<?php

namespace Database\Seeders;

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Application\Admin\Contracts\PasswordHasherInterface;
use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\Entities\Role;
use App\Domain\Admin\Permissions\Permission;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Domain\Admin\ValueObjects\RoleId;
use Illuminate\Database\Seeder;

/**
 * Seeds system roles and the two bootstrap super admins.
 * Idempotent: existing admins keep their current password.
 *
 * Override defaults with ADMIN_SEED_PASSWORD in .env.
 */
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $roles = app(RoleRepositoryInterface::class);
        $admins = app(AdminRepositoryInterface::class);
        $hasher = app(PasswordHasherInterface::class);

        $systemRoles = [
            [Role::SUPER_ADMIN_SLUG, 'Super Admin', 'Full access to every module.', Permission::all()],
            ['support', 'Support Agent', 'Monitors conversations and handles review queue / handoffs.', [
                Permission::DASHBOARD_VIEW,
                Permission::CONVERSATIONS_VIEW,
                Permission::CONVERSATIONS_LIVE,
                Permission::CONVERSATIONS_REPLY,
                Permission::CONVERSATIONS_HANDOFF_MANAGE,
                Permission::REVIEW_QUEUE_VIEW,
                Permission::REVIEW_QUEUE_MANAGE,
            ]],
            ['content-manager', 'Content Manager', 'Edits character, prompts, knowledge and rules.', [
                Permission::DASHBOARD_VIEW,
                Permission::CHARACTER_VIEW,
                Permission::CHARACTER_MANAGE,
                Permission::PROMPTS_VIEW,
                Permission::PROMPTS_MANAGE,
                Permission::KNOWLEDGE_VIEW,
                Permission::KNOWLEDGE_MANAGE,
                Permission::RULES_VIEW,
                Permission::RULES_MANAGE,
                Permission::AI_SETTINGS_VIEW,
            ]],
            ['viewer', 'Viewer', 'Read-only access.', [
                Permission::DASHBOARD_VIEW,
                Permission::CONVERSATIONS_VIEW,
                Permission::CHARACTER_VIEW,
                Permission::PROMPTS_VIEW,
                Permission::KNOWLEDGE_VIEW,
                Permission::RULES_VIEW,
                Permission::AI_SETTINGS_VIEW,
                Permission::REVIEW_QUEUE_VIEW,
                Permission::AUDIT_LOGS_VIEW,
                Permission::PIPELINE_LOGS_VIEW,
            ]],
        ];

        foreach ($systemRoles as [$slug, $name, $description, $permissions]) {
            $existing = $roles->findBySlug($slug);
            if ($existing !== null) {
                if (! $existing->isSuperAdmin()) {
                    $existing->update($name, $description, $permissions);
                    $roles->save($existing);
                }

                continue;
            }

            $roles->save(Role::create(new RoleId(bin2hex(random_bytes(16))), $slug, $name, $description, $permissions, isSystem: true));
            $this->command?->line("  role: {$slug}");
        }

        $password = (string) env('ADMIN_SEED_PASSWORD', '123456');
        foreach (['amirreza' => 'Amirreza', 'danial' => 'Danial'] as $username => $name) {
            if ($admins->findByUsername($username) !== null) {
                continue;
            }

            $admins->save(Admin::create(
                adminId: new AdminId(bin2hex(random_bytes(16))),
                username: $username,
                name: $name,
                email: null,
                passwordHash: $hasher->hash($password),
                isSuperAdmin: true,
                locale: 'fa',
                mustChangePassword: false,
            ));
            $this->command?->line("  super admin: {$username}");
        }
    }
}

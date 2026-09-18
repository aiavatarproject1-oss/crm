<?php

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Application\Admin\Services\AdminAccessResolver;
use App\Domain\Admin\Permissions\Permission;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminDocument;
use Illuminate\Support\Facades\Broadcast;

/*
| Private admin channels (Reverb). Authorized with the `admin` Sanctum guard —
| the Next.js app proxies /broadcasting/auth with the admin Bearer token.
*/

$adminCan = static function (mixed $user, string $permission): bool {
    if (! $user instanceof AdminDocument) {
        return false;
    }

    $admin = app(AdminRepositoryInterface::class)->find(new AdminId((string) $user->getAttribute('_id')));

    return $admin !== null && $admin->isActive() && app(AdminAccessResolver::class)->can($admin, $permission);
};

Broadcast::channel('admin.conversations', fn ($user) => $adminCan($user, Permission::CONVERSATIONS_LIVE), ['guards' => ['admin']]);
Broadcast::channel('admin.conversations.{conversationId}', fn ($user, string $conversationId) => $adminCan($user, Permission::CONVERSATIONS_LIVE), ['guards' => ['admin']]);
Broadcast::channel('admin.pipeline', fn ($user) => $adminCan($user, Permission::PIPELINE_LOGS_VIEW), ['guards' => ['admin']]);
Broadcast::channel('admin.review', fn ($user) => $adminCan($user, Permission::REVIEW_QUEUE_VIEW), ['guards' => ['admin']]);

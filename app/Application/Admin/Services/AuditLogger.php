<?php

namespace App\Application\Admin\Services;

use App\Application\Admin\Contracts\AuditLogRepositoryInterface;
use App\Application\Observability\CorrelationContext;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\Entities\AuditLog;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records admin actions to the DB (`audit_logs`) and to the `audit` file channel.
 * Never throws — auditing must not break the primary action.
 */
final class AuditLogger
{
    /** @var array{ip: ?string, user_agent: ?string} */
    private array $request = ['ip' => null, 'user_agent' => null];

    public function __construct(
        private readonly AuditLogRepositoryInterface $repository,
        private readonly CorrelationContext $correlation,
    ) {}

    public function withRequest(?string $ip, ?string $userAgent): void
    {
        $this->request = ['ip' => $ip, 'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 300)];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $context
     */
    public function record(
        ?Admin $actor,
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        array $changes = [],
        array $context = [],
    ): void {
        $log = new AuditLog(
            id: bin2hex(random_bytes(16)),
            actorId: $actor === null ? null : (string) $actor->id(),
            actorUsername: $actor?->username(),
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            changes: self::redact($changes),
            context: self::redact($context),
            ip: $this->request['ip'],
            userAgent: $this->request['user_agent'],
            correlationId: $this->correlation->get(),
            createdAt: new DateTimeImmutable,
        );

        try {
            $this->repository->append($log);
        } catch (Throwable $exception) {
            Log::error('audit.persist_failed', ['error' => $exception->getMessage(), 'action' => $action]);
        }

        try {
            Log::channel('audit')->info($action, [
                'actor' => $log->actorUsername,
                'actor_id' => $log->actorId,
                'target_type' => $log->targetType,
                'target_id' => $log->targetId,
                'changes' => $log->changes,
                'context' => $log->context,
                'ip' => $log->ip,
                'correlation_id' => $log->correlationId,
            ]);
        } catch (Throwable) {
            // file channel misconfigured — DB copy already written
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function redact(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'password') || str_contains($lower, 'token') || str_contains($lower, 'secret')) {
                $clean[$key] = '[redacted]';

                continue;
            }
            $clean[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $clean;
    }
}

<?php

namespace App\Application\AI\DTO;

use App\Application\DTO\RuleEvaluationResult;
use App\Application\Quality\DTO\QualityResult;
use App\Domain\Quality\ValueObjects\ResponseDecision;

final readonly class AiPipelineResult
{
    public function __construct(public RuleEvaluationResult $rule, public ?LlmResponse $response, public ?string $assistant_message_id, public ResponseDecision $decision, public ?QualityResult $quality = null, public ?string $admin_task_id = null) {}
}

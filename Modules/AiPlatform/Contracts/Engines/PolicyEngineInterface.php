<?php

// عقد محرك السياسات — يُقيَّم قبل أي استدعاء
namespace Modules\AiPlatform\Contracts\Engines;

use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\PolicyDecisionDTO;

interface PolicyEngineInterface
{
    /**
     * تقييم السياسات قبل أي استدعاء
     * DENY → لا Token يُستهلك
     */
    public function evaluate(
        ExecutionRequestDTO $request,
        ?int   $userId,
        array  $userRoles,
    ): PolicyDecisionDTO;
}

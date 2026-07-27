<?php

// عقد محرك التكاليف
namespace Modules\AiPlatform\Contracts\Engines;

use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

interface CostEngineInterface
{
    /** تسجيل تكلفة طلب مكتمل + تحديث الميزانيات */
    public function record(
        ExecutionRequestDTO $request,
        ExecutionResultDTO  $result,
    ): void;

    /**
     * التحقق من الميزانية قبل التنفيذ
     * يُعيد true إذا كانت الميزانية متاحة
     */
    public function checkBudget(int $companyId, ?int $agentId, ?int $userId): bool;

    /** تقرير تكاليف فترة زمنية */
    public function report(int $companyId, string $period, ?int $agentId = null): array;
}

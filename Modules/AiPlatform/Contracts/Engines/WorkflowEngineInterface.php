<?php

// عقد محرك الـ Workflows
namespace Modules\AiPlatform\Contracts\Engines;

use Modules\AiPlatform\DTOs\WorkflowStateDTO;

interface WorkflowEngineInterface
{
    /** تشغيل Workflow Sync */
    public function run(string $workflowKey, int $companyId, array $input): WorkflowStateDTO;

    /**
     * تشغيل Workflow Async
     * يُعيد ULID الـ WorkflowRun
     */
    public function dispatch(string $workflowKey, int $companyId, array $input): string;

    /** حالة Workflow جارٍ */
    public function status(string $runUlid): WorkflowStateDTO;

    /** إلغاء Workflow جارٍ */
    public function cancel(string $runUlid): bool;
}

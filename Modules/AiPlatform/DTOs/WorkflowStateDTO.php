<?php

// حالة Workflow Run
namespace Modules\AiPlatform\DTOs;

final class WorkflowStateDTO
{
    public function __construct(
        public readonly string  $runUlid,
        public readonly string  $status,        // 'pending'|'running'|'completed'|'failed'|'cancelled'
        public readonly int     $currentStep,
        public readonly int     $totalSteps,
        public readonly array   $stepResults,
        public readonly ?string $errorMessage = null,
    ) {}

    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isFailed(): bool    { return $this->status === 'failed'; }
    public function isRunning(): bool   { return $this->status === 'running'; }
}

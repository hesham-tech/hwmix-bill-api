<?php

// وظيفة خلفية لتنفيذ الـ Workflows عبر Queue
namespace Modules\AiPlatform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AiPlatform\Contracts\Engines\WorkflowEngineInterface;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $workflowKey,
        public readonly int $companyId,
        public readonly array $input,
        public readonly string $runUlid
    ) {}

    public function handle(WorkflowEngineInterface $workflowEngine): void
    {
        $workflowEngine->run($this->workflowKey, $this->companyId, $this->input);
    }
}

<?php

namespace Modules\AiPlatform\Builders;

use Illuminate\Contracts\Foundation\Application;
use Modules\AiPlatform\Contracts\Engines\WorkflowEngineInterface;
use Modules\AiPlatform\DTOs\WorkflowStateDTO;

class WorkflowBuilder
{
    protected ?int $companyId = null;
    protected array $input = [];

    public function __construct(
        protected string $workflowKey,
        protected Application $app
    ) {}

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function with(array $input): self
    {
        $this->input = array_merge($this->input, $input);
        return $this;
    }

    public function run(): WorkflowStateDTO
    {
        /** @var WorkflowEngineInterface $engine */
        $engine = $this->app->make(WorkflowEngineInterface::class);
        return $engine->run($this->workflowKey, $this->companyId ?? 0, $this->input);
    }

    public function dispatch(): string
    {
        /** @var WorkflowEngineInterface $engine */
        $engine = $this->app->make(WorkflowEngineInterface::class);
        return $engine->dispatch($this->workflowKey, $this->companyId ?? 0, $this->input);
    }
}

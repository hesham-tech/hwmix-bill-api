<?php

namespace Modules\AiPlatform\Builders;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

class CapabilityBuilder
{
    protected ?int $companyId = null;
    protected ?int $promptId = null;
    protected string $promptKey = '';
    protected array $variables = [];
    protected array $options = [];
    protected ?int $requestedBy = null;

    public function __construct(
        protected string $capabilityKey,
        protected Application $app
    ) {}

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function prompt(string $promptKey): self
    {
        $this->promptKey = $promptKey;
        return $this;
    }

    public function with(array $variables): self
    {
        $this->variables = array_merge($this->variables, $variables);
        return $this;
    }

    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function byUser(?int $userId): self
    {
        $this->requestedBy = $userId;
        return $this;
    }

    public function run(): ExecutionResultDTO
    {
        Log::info("[AI Milestone Trace] [Builder Enter] CapabilityKey: {$this->capabilityKey}, PromptKey: {$this->promptKey}");

        $request = new ExecutionRequestDTO(
            capabilityKey: $this->capabilityKey,
            sourceType: 'direct',
            companyId: $this->companyId,
            promptKey: $this->promptKey,
            promptVariables: $this->variables,
            options: $this->options,
            stream: false,
            requestedBy: $this->requestedBy,
        );

        /** @var ExecutionEngineInterface $engine */
        $engine = $this->app->make(ExecutionEngineInterface::class);
        $result = $engine->run($request);

        Log::info("[AI Milestone Trace] [Builder Exit] Success: " . ($result->successful ? 'true' : 'false'));
        return $result;
    }

    public function stream(callable $callback): void
    {
        $request = new ExecutionRequestDTO(
            capabilityKey: $this->capabilityKey,
            sourceType: 'direct',
            companyId: $this->companyId,
            promptKey: $this->promptKey,
            promptVariables: $this->variables,
            options: $this->options,
            stream: true,
            requestedBy: $this->requestedBy,
        );

        /** @var ExecutionEngineInterface $engine */
        $engine = $this->app->make(ExecutionEngineInterface::class);
        $generator = $engine->stream($request);

        foreach ($generator as $chunk) {
            $callback($chunk);
        }
    }
}

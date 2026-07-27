<?php

namespace Modules\AiPlatform\Builders;

use Illuminate\Contracts\Foundation\Application;
use Modules\AiPlatform\Contracts\Engines\AgentEngineInterface;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

class AgentBuilder
{
    protected ?int $companyId = null;
    protected ?int $userId = null;
    protected string $conversationUlid = '';
    protected string $messageText = '';

    public function __construct(
        protected string $agentKey,
        protected Application $app
    ) {}

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function byUser(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function conversation(string $ulid): self
    {
        $this->conversationUlid = $ulid;
        return $this;
    }

    public function message(string $message): self
    {
        $this->messageText = $message;
        return $this;
    }

    public function run(): ExecutionResultDTO
    {
        /** @var AgentEngineInterface $engine */
        $engine = $this->app->make(AgentEngineInterface::class);

        // إذا لم تكن المحادثة ممررة، سيتم الحصول على معرّف الوكيل لاحقاً
        return $engine->chat(
            agentId: 0, // Mock for builder interface binding
            userMessage: $this->messageText,
            conversationUlid: $this->conversationUlid,
            companyId: $this->companyId ?? 0,
            userId: $this->userId
        );
    }
}

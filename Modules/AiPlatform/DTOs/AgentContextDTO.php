<?php

// سياق الوكيل المكتمل
namespace Modules\AiPlatform\DTOs;

final class AgentContextDTO
{
    public function __construct(
        public readonly int   $agentId,
        public readonly int   $companyId,
        public readonly ?int  $userId,
        public readonly array $memories             = [],
        public readonly array $knowledgeChunks      = [],
        public readonly array $conversationHistory  = [],
        public readonly array $availableTools       = [],
        public readonly array $metadata             = [],
    ) {}
}

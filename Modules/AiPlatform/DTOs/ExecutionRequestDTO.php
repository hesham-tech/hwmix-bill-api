<?php

// بيانات طلب التنفيذ
namespace Modules\AiPlatform\DTOs;

final class ExecutionRequestDTO
{
    public function __construct(
        public readonly string  $capabilityKey,
        public readonly string  $sourceType,        // 'direct'|'agent'|'workflow'
        public readonly ?int    $companyId,
        public readonly ?int    $agentId         = null,
        public readonly ?int    $promptId        = null,
        public readonly ?string $promptKey       = null,
        public readonly array   $promptVariables = [],
        public readonly array   $messages        = [],  // لـ Agent Chat
        public readonly array   $options         = [],  // temperature, max_tokens, ...
        public readonly bool    $stream          = false,
        public readonly ?int    $requestedBy     = null,
        public readonly ?string $conversationId = null,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

<?php

// عقد محرك الوكلاء
namespace Modules\AiPlatform\Contracts\Engines;

use Modules\AiPlatform\DTOs\AgentContextDTO;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

interface AgentEngineInterface
{
    /** بناء السياق الكامل للوكيل */
    public function buildContext(
        int    $agentId,
        int    $companyId,
        ?int   $userId,
        string $conversationUlid,
    ): AgentContextDTO;

    /** تشغيل الوكيل برسالة مستخدم */
    public function chat(
        int    $agentId,
        string $userMessage,
        string $conversationUlid,
        int    $companyId,
        ?int   $userId,
    ): ExecutionResultDTO;

    /**
     * إنشاء محادثة جديدة
     * يُعيد ULID المحادثة
     */
    public function createConversation(
        int  $agentId,
        int  $companyId,
        ?int $userId,
    ): string;
}

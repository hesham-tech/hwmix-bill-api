<?php

// عقد كل Tool — تُسجَّل من Plugins
namespace Modules\AiPlatform\Contracts\Tools;

use Modules\AiPlatform\DTOs\ToolExecutionResultDTO;

interface AiToolInterface
{
    /** المفتاح الفريد — يجب أن يطابق ai_tools.key */
    public function name(): string;

    /** وصف وظيفة الـ Tool — يُرسَل للنموذج */
    public function description(): string;

    /** JSON Schema للـ Input */
    public function inputSchema(): array;

    /**
     * تنفيذ الـ Tool
     * $context: ['company_id', 'user_id', 'agent_id', 'conversation_ulid']
     */
    public function execute(array $params, array $context): ToolExecutionResultDTO;

    /** الصلاحية المطلوبة — null = لا صلاحية */
    public function requiredPermission(): ?string;

    /** هل يعمل عبر Queue؟ */
    public function isAsync(): bool;

    /** مهلة التنفيذ بالثواني */
    public function timeout(): int;
}

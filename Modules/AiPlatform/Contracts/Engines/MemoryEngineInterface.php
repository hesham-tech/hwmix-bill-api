<?php

// عقد محرك الذاكرة
namespace Modules\AiPlatform\Contracts\Engines;

interface MemoryEngineInterface
{
    /** جلب الذاكرة بناءً على النوع والنطاق */
    public function recall(
        int    $companyId,
        string $type,
        int    $scopeId,
        int    $limit = 10,
    ): array;

    /** جلب ذاكرة المحادثة */
    public function recallConversation(string $conversationUlid, int $limit = 20): array;

    /** حفظ في الذاكرة */
    public function remember(
        int    $companyId,
        string $type,
        int    $scopeId,
        string $key,
        mixed  $value,
        ?int   $ttlSeconds = null,
    ): void;

    /** حذف من الذاكرة */
    public function forget(int $companyId, string $type, int $scopeId, string $key): void;
}

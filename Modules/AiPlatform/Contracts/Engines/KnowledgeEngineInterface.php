<?php

// عقد محرك المعرفة (RAG)
namespace Modules\AiPlatform\Contracts\Engines;

interface KnowledgeEngineInterface
{
    /**
     * إضافة نص للقاعدة المعرفية
     * يُعيد عدد الـ Chunks المُضافة
     */
    public function ingest(
        int    $knowledgeBaseId,
        string $content,
        string $sourceLabel,
        array  $metadata = [],
    ): int;

    /** البحث الدلالي */
    public function search(
        int    $knowledgeBaseId,
        string $query,
        int    $topK = 5,
        float  $minSimilarity = 0.7,
    ): array;

    /** حذف مصدر من القاعدة المعرفية */
    public function forget(int $knowledgeBaseId, string $sourceLabel): void;
}

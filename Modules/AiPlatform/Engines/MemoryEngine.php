<?php

namespace Modules\AiPlatform\Engines;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\AiPlatform\Contracts\MemoryEngineInterface;

/**
 * محرك إدارة الذاكرة متعددة الأنواع والنطاقات للوكلاء والمحادثات.
 */
class MemoryEngine implements MemoryEngineInterface
{
    public function recall(int $companyId, string $type, int $scopeId, int $limit = 10)
    {
        return DB::table('ai_memories')
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('scope_id', $scopeId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function recallConversation(string $conversationUlid, int $limit = 20)
    {
        return DB::table('ai_messages')
            ->where('conversation_ulid', $conversationUlid)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function remember(int $companyId, string $type, int $scopeId, string $key, mixed $value, ?int $ttlSeconds = null)
    {
        $expiresAt = $ttlSeconds ? Carbon::now()->addSeconds($ttlSeconds) : null;

        return DB::table('ai_memories')->updateOrInsert(
            [
                'company_id' => $companyId,
                'type' => $type,
                'scope_id' => $scopeId,
                'key' => $key,
            ],
            [
                'value' => is_array($value) || is_object($value) ? json_encode($value) : $value,
                'expires_at' => $expiresAt,
                'updated_at' => Carbon::now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }

    public function forget(int $companyId, string $type, int $scopeId, string $key)
    {
        return DB::table('ai_memories')
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->delete();
    }
}

<?php

// أنواع الذاكرة في المنصة
namespace Modules\AiPlatform\Enums;

enum MemoryType: string
{
    case Conversation = 'conversation'; // ذاكرة محادثة محددة
    case Session      = 'session';      // ذاكرة جلسة مؤقتة
    case User         = 'user';         // ذاكرة مستخدم دائمة
    case Agent        = 'agent';        // ذاكرة وكيل
    case Shared       = 'shared';       // ذاكرة مشتركة بين وكلاء الشركة
    case Pinned       = 'pinned';       // ذاكرة مثبتة لا تنتهي

    public function label(): string
    {
        return match($this) {
            self::Conversation => 'ذاكرة المحادثة',
            self::Session      => 'ذاكرة الجلسة',
            self::User         => 'ذاكرة المستخدم',
            self::Agent        => 'ذاكرة الوكيل',
            self::Shared       => 'ذاكرة مشتركة',
            self::Pinned       => 'ذاكرة مثبتة',
        };
    }

    public function defaultTtlSeconds(): ?int
    {
        return match($this) {
            self::Conversation => config('ai-platform.memory.conversation_ttl', 86400 * 30),
            self::Session      => config('ai-platform.memory.session_ttl', 3600),
            self::User         => config('ai-platform.memory.user_ttl'),
            self::Agent        => null,
            self::Shared       => null,
            self::Pinned       => null,
        };
    }
}

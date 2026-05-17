<?php

namespace Modules\Core\Services;

/**
 * الكلاس الأساسي للخدمات (Services)
 */
abstract class BaseService
{
    /**
     * دالة مساعدة لتسجيل الأخطاء أو العمليات المشتركة.
     */
    protected function logInfo(string $message, array $context = [])
    {
        \Illuminate\Support\Facades\Log::info($message, $context);
    }

    protected function logError(string $message, array $context = [])
    {
        \Illuminate\Support\Facades\Log::error($message, $context);
    }
}

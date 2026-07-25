<?php
// خدمة Stub معمارية نظيفة تُمثل نقطة التوسع الجاهزة للتكامل مع محركات الذكاء الاصطناعي مستقبلاً.

namespace Modules\HwnixCash\Services;

use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;

class HwnixCashMessageParserService implements HwnixCashMessageParserInterface
{
    /**
     * نقطة التوسع المعمارية النظيفة (Stub Placeholder).
     * سيتم ربط محرك الذكاء الاصطناعي أو التحليل المتقدم هنا في الإصدار القادم دون تعديل البنية.
     */
    public function parse(SmsMessage $message): void
    {
        // لا يتم تنفيذ أي كود تحليل أو Parsing مؤقت حالياً.
        // لا يتم إنشاء أي معاملة في جدول WalletTransactions في هذه المرحلة.
        \Log::info("Message passed to HwnixCashMessageParserService Extension Point: ID {$message->id}, Sender: {$message->phoneNumber}");
    }
}

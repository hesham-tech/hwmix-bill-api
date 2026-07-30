<?php
// كلاس تحديث وتوجيه حالة الرسائل الواردة النهائية بعد إتمام المعالجة أو التدقيق.

namespace Modules\HwnixCash\Services\Processing;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Models\HwnixCashMessage;

class SmsMessageFinalizer
{
    /**
     * تحديث حالة الرسالة القصيرة إلى معالجة بنجاح (processed).
     */
    public function markAsProcessed(int $messageId): void
    {
        HwnixCashMessage::where('id', $messageId)->update(['status' => 'processed']);
        Log::info("[SmsMessageFinalizer] Marked Message ID {$messageId} as processed.");
    }

    /**
     * توجيه الرسالة التي تحتوي على غموض أو نوع غير معروف لقائمة المراجعة البشرية (needs_review).
     */
    public function markAsNeedsReview(int $messageId, string $reason = 'نوع الحركة غير معروف أو يتطلب التدقيق البشرى'): void
    {
        HwnixCashMessage::where('id', $messageId)->update([
            'status' => 'needs_review',
            'error_message' => $reason,
        ]);
        Log::warning("[SmsMessageFinalizer] Marked Message ID {$messageId} as needs_review. Reason: {$reason}");
    }
}

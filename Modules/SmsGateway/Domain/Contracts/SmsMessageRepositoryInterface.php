<?php
// واجهة مستودع البيانات (Repository) الخاص برسائل الـ SMS.

namespace Modules\SmsGateway\Domain\Contracts;

use Modules\SmsGateway\Domain\Entities\SmsMessage;
use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;

interface SmsMessageRepositoryInterface
{
    /**
     * البحث عن رسالة بواسطة معرفها الرقمي.
     */
    public function findById(int $id): ?SmsMessage;

    /**
     * حفظ أو تحديث بيانات الرسالة.
     */
    public function save(SmsMessage $message): SmsMessage;

    /**
     * جلب الرسائل الصادرة المعلقة المخصصة لجهاز إرسال معين.
     */
    public function getPendingOutgoing(int $deviceId, int $limit = 50): array;

    /**
     * تحديث حالة الرسالة.
     */
    public function updateStatus(int $messageId, SmsMessageStatus $status, ?string $reason = null): bool;

    /**
     * التحقق من تكرار الرسالة الواردة بناءً على المعرف المحلي المخزن بالهاتف لمنع الازدواجية.
     */
    public function isIncomingDuplicate(int $deviceId, string $messageRef): bool;

    /**
     * الحصول على رسائل شركة محددة مع الفلترة والتقسيم.
     */
    public function getCompanyMessages(int $companyId, array $filters = []): array;
}

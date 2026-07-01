<?php
// خدمة معالجة إرسال واستقبال رسائل الـ SMS ومزامنة حركاتها وتطبيق الـ Drivers.

namespace Modules\SmsGateway\Services;

use Modules\SmsGateway\Domain\Contracts\SmsMessageRepositoryInterface;
use Modules\SmsGateway\Domain\Entities\SmsMessage as SmsMessageEntity;
use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;
use Modules\SmsGateway\Domain\Enums\CommandStatus;
use Modules\SmsGateway\Models\SmsDevice;
use Modules\SmsGateway\Models\SmsDeviceCommand;
use Modules\SmsGateway\Models\SmsMessage;
use Modules\SmsGateway\Models\SmsLine;
use Illuminate\Support\Facades\DB;

class SmsDispatcherService
{
    public function __construct(
        protected SmsMessageRepositoryInterface $messageRepo
    ) {}

    /**
     * معالجة رسالة واردة جديدة من الهاتف.
     */
    public function processIncomingSms(array $data, int $companyId, int $userId): SmsMessageEntity
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            $deviceId = $data['device_id'];
            $messageRef = $data['message_ref']; // معرف الرسالة على هاتف أندرويد

            // التحقق من الـ Idempotency لمنع الازدواجية
            $isDuplicate = $this->messageRepo->isIncomingDuplicate($deviceId, $messageRef);
            if ($isDuplicate) {
                // استرجاع السجل الموجود مسبقاً وتجنب إعادة الإدراج
                $existing = SmsMessage::where('sms_device_id', $deviceId)
                    ->where('message_ref', $messageRef)
                    ->where('direction', 'incoming')
                    ->firstOrFail();
                return $this->mapToEntity($existing);
            }

            // البحث عن الشريحة المستلمة لمطابقتها بالـ line_id
            $line = SmsLine::where('sms_device_id', $deviceId)
                ->where('subscription_id', $data['subscription_id'])
                ->first();

            $message = new SmsMessageEntity(
                id: null,
                companyId: $companyId,
                createdBy: $userId,
                deviceId: $deviceId,
                lineId: $line?->id,
                phoneNumber: $data['phone_number'],
                messageBody: $data['message_body'],
                direction: 'incoming',
                status: SmsMessageStatus::Delivered,
                messageRef: $messageRef,
                sentAt: isset($data['sent_at']) ? new \DateTime($data['sent_at']) : now(),
                deliveredAt: now()
            );

            $savedMessage = $this->messageRepo->save($message);

            // إطلاق حدث استلام رسالة واردة
            event(new \Modules\SmsGateway\Events\SmsReceived($savedMessage));

            return $savedMessage;
        });
    }

    /**
     * معالجة الإرسال عبر الـ Driver المناسب للخط النشط.
     */
    public function dispatchOutgoingSms(array $data, int $companyId, int $userId): SmsMessageEntity
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            $line = SmsLine::findOrFail($data['sms_line_id']);

            $message = new SmsMessageEntity(
                id: null,
                companyId: $companyId,
                createdBy: $userId,
                deviceId: $line->sms_device_id,
                lineId: $line->id,
                phoneNumber: $data['phone_number'],
                messageBody: $data['message_body'],
                direction: 'outgoing',
                status: SmsMessageStatus::Queued
            );

            // 1. حفظ سجل الرسالة الأولي
            $savedMessage = $this->messageRepo->save($message);

            // 2. حل السائق المناسب للإرسال (تطبيق الـ Driver Pattern)
            $driver = $this->resolveDriver($line);

            // 3. تنفيذ الإرسال وتحديث الحالة المرجعة من السائق
            $status = $driver->send($savedMessage);

            if ($status !== SmsMessageStatus::Queued) {
                $this->messageRepo->updateStatus($savedMessage->id, $status);
                $savedMessage->status = $status;
            }

            return $savedMessage;
        });
    }

    /**
     * تحديد وحل سائق بوابة النقل المناسب للشريحة المحددة.
     */
    private function resolveDriver(SmsLine $line): \Modules\SmsGateway\Domain\Contracts\SmsTransportDriverInterface
    {
        // حالياً نستخدم سائق جهاز الأندرويد لجميع الخطوط
        // مستقبلاً يمكن التبديل بناءً على نوع أو تفضيلات الخط (مثلاً GoIp أو Twilio)
        return app(\Modules\SmsGateway\Drivers\AndroidAgentDriver::class);
    }

    /**
     * تحويل Eloquent Model إلى Domain Entity (داخلي للـ duplication check).
     */
    private function mapToEntity(SmsMessage $model): SmsMessageEntity
    {
        return new SmsMessageEntity(
            id: $model->id,
            companyId: $model->company_id,
            createdBy: $model->created_by,
            deviceId: $model->sms_device_id,
            lineId: $model->sms_line_id,
            phoneNumber: $model->phone_number,
            messageBody: $model->message_body,
            direction: $model->direction,
            status: SmsMessageStatus::from($model->status),
            failureReason: $model->failure_reason,
            retryCount: $model->retry_count,
            messageRef: $model->message_ref,
            sentAt: $model->sent_at,
            deliveredAt: $model->delivered_at,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at
        );
    }
}

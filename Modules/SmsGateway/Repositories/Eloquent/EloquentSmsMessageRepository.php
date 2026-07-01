<?php
// تنفيذ مستودع بيانات رسائل الـ SMS باستخدام Eloquent ORM.

namespace Modules\SmsGateway\Repositories\Eloquent;

use Modules\SmsGateway\Domain\Contracts\SmsMessageRepositoryInterface;
use Modules\SmsGateway\Domain\Entities\SmsMessage;
use Modules\SmsGateway\Domain\Enums\SmsMessageStatus;
use Modules\SmsGateway\Models\SmsMessage as EloquentSmsMessage;

class EloquentSmsMessageRepository implements SmsMessageRepositoryInterface
{
    /**
     * تحويل Eloquent Model إلى Domain Entity.
     */
    private function mapToEntity(EloquentSmsMessage $model): SmsMessage
    {
        return new SmsMessage(
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

    /**
     * البحث عن رسالة بواسطة معرفها الرقمي.
     */
    public function findById(int $id): ?SmsMessage
    {
        $model = EloquentSmsMessage::find($id);
        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * حفظ أو تحديث بيانات الرسالة.
     */
    public function save(SmsMessage $message): SmsMessage
    {
        $data = [
            'company_id' => $message->companyId,
            'created_by' => $message->createdBy,
            'sms_device_id' => $message->deviceId,
            'sms_line_id' => $message->lineId,
            'phone_number' => $message->phoneNumber,
            'message_body' => $message->messageBody,
            'direction' => $message->direction,
            'status' => $message->status->value,
            'failure_reason' => $message->failureReason,
            'retry_count' => $message->retryCount,
            'message_ref' => $message->messageRef,
            'sent_at' => $message->sentAt,
            'delivered_at' => $message->deliveredAt,
        ];

        if ($message->id) {
            $model = EloquentSmsMessage::findOrFail($message->id);
            $model->update($data);
        } else {
            $model = EloquentSmsMessage::create($data);
        }

        return $this->mapToEntity($model);
    }

    /**
     * جلب الرسائل الصادرة المعلقة المخصصة لجهاز إرسال معين.
     */
    public function getPendingOutgoing(int $deviceId, int $limit = 50): array
    {
        $models = EloquentSmsMessage::where('sms_device_id', $deviceId)
            ->where('direction', 'outgoing')
            ->where('status', SmsMessageStatus::Queued->value)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->mapToEntity($model))->toArray();
    }

    /**
     * تحديث حالة الرسالة.
     */
    public function updateStatus(int $messageId, SmsMessageStatus $status, ?string $reason = null): bool
    {
        $model = EloquentSmsMessage::find($messageId);
        if (!$model) {
            return false;
        }

        $updates = ['status' => $status->value];
        
        if ($reason) {
            $updates['failure_reason'] = $reason;
        }

        if ($status === SmsMessageStatus::Sent) {
            $updates['sent_at'] = now();
        } elseif ($status === SmsMessageStatus::Delivered) {
            $updates['delivered_at'] = now();
        }

        return $model->update($updates);
    }

    /**
     * التحقق من تكرار الرسالة الواردة بناءً على المعرف المحلي المخزن بالهاتف لمنع الازدواجية.
     */
    public function isIncomingDuplicate(int $deviceId, string $messageRef): bool
    {
        return EloquentSmsMessage::where('sms_device_id', $deviceId)
            ->where('message_ref', $messageRef)
            ->where('direction', 'incoming')
            ->exists();
    }

    /**
     * الحصول على رسائل شركة محددة مع الفلترة والتقسيم.
     */
    public function getCompanyMessages(int $companyId, array $filters = []): array
    {
        $query = EloquentSmsMessage::where('company_id', $companyId);

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['device_id'])) {
            $query->where('sms_device_id', $filters['device_id']);
        }

        $models = $query->orderBy('id', 'desc')->get();
        return $models->map(fn($model) => $this->mapToEntity($model))->toArray();
    }
}

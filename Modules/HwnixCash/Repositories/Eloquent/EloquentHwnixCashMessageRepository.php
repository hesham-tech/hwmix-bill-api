<?php
// المستودع الفعلي لإدارة رسائل كاش هونكس باستعمال Eloquent.

namespace Modules\HwnixCash\Repositories\Eloquent;

use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\Domain\Enums\SmsMessageStatus;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\DTOs\OutgoingSmsData;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Models\HwnixCashMessage;

class EloquentHwnixCashMessageRepository implements HwnixCashMessageRepositoryInterface
{
    public function findById(int $id): ?SmsMessage
    {
        $model = HwnixCashMessage::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function createIncoming(IncomingSmsData $dto, int $companyId, int $userId): SmsMessage
    {
        $line = HwnixCashLine::where('subscription_id', $dto->subscriptionId)
            ->where('company_id', $companyId)
            ->first();

        $message = HwnixCashMessage::create([
            'company_id' => $companyId,
            'created_by' => $userId,
            'sms_device_id' => $dto->deviceId,
            'sms_line_id' => $line?->id,
            'phone_number' => $dto->phoneNumber,
            'message_body' => $dto->messageBody,
            'direction' => 'incoming',
            'status' => 'received',
            'message_ref' => $dto->messageRef,
            'sent_at' => $dto->sentAt ?? now(),
        ]);

        return $this->toEntity($message);
    }

    public function createOutgoing(OutgoingSmsData $dto, int $companyId, int $userId): SmsMessage
    {
        $line = HwnixCashLine::find($dto->smsLineId);

        $message = HwnixCashMessage::create([
            'company_id' => $companyId,
            'created_by' => $userId,
            'sms_device_id' => $line?->device?->id,
            'sms_line_id' => $dto->smsLineId,
            'phone_number' => $dto->phoneNumber,
            'message_body' => $dto->messageBody,
            'direction' => 'outgoing',
            'status' => 'queued',
        ]);

        return $this->toEntity($message);
    }

    public function updateStatus(int $messageId, string $status, ?string $errorCode = null, ?string $errorMessage = null): bool
    {
        return (bool) HwnixCashMessage::where('id', $messageId)->update([
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'sent_at' => $status === 'sent' || $status === 'delivered' ? now() : null,
        ]);
    }

    public function isDuplicateIncoming(int $deviceId, string $messageRef): bool
    {
        return HwnixCashMessage::where('sms_device_id', $deviceId)
            ->where('message_ref', $messageRef)
            ->where('direction', 'incoming')
            ->exists();
    }

    protected function toEntity(HwnixCashMessage $model): SmsMessage
    {
        return new SmsMessage(
            id: $model->id,
            companyId: $model->company_id,
            createdBy: $model->created_by,
            smsDeviceId: $model->sms_device_id,
            smsLineId: $model->sms_line_id,
            phoneNumber: $model->phone_number,
            messageBody: $model->message_body,
            direction: $model->direction,
            status: $model->status instanceof SmsMessageStatus ? $model->status : SmsMessageStatus::from($model->status),
            messageRef: $model->message_ref,
            errorCode: $model->error_code,
            errorMessage: $model->error_message,
            sentAt: $model->sent_at?->toIso8601String()
        );
    }
}

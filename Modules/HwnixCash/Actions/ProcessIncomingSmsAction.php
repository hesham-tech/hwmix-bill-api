<?php
// إجراء معالجة الرسائل الواردة وفحص مصادرها المعتمدة وتمريرها لنقطة التوسع.

namespace Modules\HwnixCash\Actions;

use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageRepositoryInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageSourceRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\Domain\Enums\SmsMessageStatus;
use Modules\HwnixCash\DTOs\IncomingSmsData;
use Modules\HwnixCash\Models\HwnixCashMessage;

class ProcessIncomingSmsAction
{
    public function __construct(
        protected HwnixCashMessageRepositoryInterface $messageRepo,
        protected HwnixCashMessageSourceRepositoryInterface $sourceRepo,
        protected HwnixCashMessageParserInterface $messageParser
    ) {}

    public function execute(IncomingSmsData $dto, int $companyId, int $userId): SmsMessage
    {
        // 1. حفظ الرسالة أولاً في جدول الرسائل الحالية
        if ($this->messageRepo->isDuplicateIncoming($dto->deviceId, $dto->messageRef)) {
            $existing = HwnixCashMessage::where('sms_device_id', $dto->deviceId)
                ->where('message_ref', $dto->messageRef)
                ->first();

            \Illuminate\Support\Facades\Log::warning("⚠️ [HWNixCash SMS Pipeline] Step 2/5: Duplicate incoming SMS detected (Ref: {$dto->messageRef}, DeviceID: {$dto->deviceId}). Using existing Message ID: {$existing->id}");

            $messageEntity = new SmsMessage(
                id: $existing->id,
                companyId: $existing->company_id,
                createdBy: $existing->created_by,
                smsDeviceId: $existing->sms_device_id,
                smsLineId: $existing->sms_line_id,
                phoneNumber: $existing->phone_number,
                messageBody: $existing->message_body,
                direction: $existing->direction,
                status: $existing->status instanceof SmsMessageStatus ? $existing->status : SmsMessageStatus::from($existing->status),
                messageRef: $existing->message_ref,
                errorCode: $existing->error_code,
                errorMessage: $existing->error_message,
                sentAt: $existing->sent_at?->toIso8601String()
            );
        } else {
            $messageEntity = $this->messageRepo->createIncoming($dto, $companyId, $userId);
            \Illuminate\Support\Facades\Log::info("💾 [HWNixCash SMS Pipeline] Step 2/5: Saved incoming SMS to database. Message ID: {$messageEntity->id}, Sender Phone/Identifier: '{$messageEntity->phoneNumber}'");
        }

        // 2. فحص ما إذا كانت الرسالة قادمة من مصدر معتمد ومفعل بالشركة
        $matchingSource = $this->sourceRepo->findActiveByIdentifier($messageEntity->phoneNumber, $companyId);

        // 3. إن كان هناك مصدر معتمد ومفعل: تمرير الرسالة إلى نقطة التوسع المعمارية
        if ($matchingSource) {
            \Illuminate\Support\Facades\Log::info("🔍 [HWNixCash SMS Pipeline] Step 3/5: Sender identifier '{$messageEntity->phoneNumber}' MATCHED active Message Source ID {$matchingSource->id} (Provider: '{$matchingSource->provider->value}'). Proceeding to AI Parsing Engine.", [
                'message_id' => $messageEntity->id,
                'source_id' => $matchingSource->id,
                'provider' => $matchingSource->provider->value,
                'sender_identifier' => $matchingSource->senderIdentifier,
            ]);
            $this->messageParser->parse($messageEntity);
        } else {
            \Illuminate\Support\Facades\Log::warning("⛔ [HWNixCash SMS Pipeline] Step 3/5: SKIPPED PARSING! Sender identifier '{$messageEntity->phoneNumber}' is NOT registered as an active Message Source for Company ID {$companyId}. Please add '{$messageEntity->phoneNumber}' to Message Sources (مصادر الرسائل المعتمدة).", [
                'message_id' => $messageEntity->id,
                'sender_phone' => $messageEntity->phoneNumber,
                'company_id' => $companyId,
            ]);
        }

        return $messageEntity;
    }
}

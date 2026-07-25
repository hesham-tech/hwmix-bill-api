<?php
// محول استجابة بيانات سجلات الرسائل القصيرة للـ API.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SmsMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sms_device_id' => $this->sms_device_id,
            'sms_line_id' => $this->sms_line_id,
            'phone_number' => $this->phone_number,
            'message_body' => $this->message_body,
            'direction' => $this->direction,
            'status' => $this->status,
            'message_ref' => $this->message_ref,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

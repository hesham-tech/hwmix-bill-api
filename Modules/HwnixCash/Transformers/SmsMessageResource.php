<?php
// محول استجابة بيانات سجلات الرسائل القصيرة للـ API مع تفاصيل الجهاز والشريحة.

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
            'sender' => $this->sender_name ?: $this->phone_number,
            'sender_name' => $this->sender_name,
            'phone_number' => $this->phone_number,
            'body' => $this->message_body,
            'message_body' => $this->message_body,
            'direction' => $this->direction,
            'status' => $this->status,
            'provider' => $this->phone_number,
            'carrier' => $this->phone_number,
            'message_ref' => $this->message_ref,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'device' => $this->device ? [
                'id' => $this->device->id,
                'name' => $this->device->name ?: ($this->device->brand . ' ' . $this->device->model),
                'device_name' => $this->device->name ?: ($this->device->brand . ' ' . $this->device->model),
            ] : null,
            'line' => $this->line ? [
                'id' => $this->line->id,
                'phone_number' => $this->line->phone_number,
                'carrier' => $this->line->carrier,
                'slot_index' => $this->line->slot_index,
            ] : null,
        ];
    }
}

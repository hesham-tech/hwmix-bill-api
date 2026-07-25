<?php
// محول استجابة بيانات أوامر الأجهزة للـ API.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceCommandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sms_device_id' => $this->sms_device_id,
            'command_type' => $this->command_type,
            'payload' => $this->payload,
            'status' => $this->status,
            'response_payload' => $this->response_payload,
            'executed_at' => $this->executed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

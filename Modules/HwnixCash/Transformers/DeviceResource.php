<?php
// محول استجابة بيانات أجهزة كاش هونكس للـ API.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'android_id' => $this->android_id,
            'uuid' => $this->uuid,
            'device_name' => $this->device_name,
            'brand' => $this->brand,
            'model' => $this->model,
            'android_version' => $this->android_version,
            'app_version' => $this->app_version,
            'capabilities' => $this->capabilities,
            'status' => $this->status,
            'is_online' => $this->isOnline(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

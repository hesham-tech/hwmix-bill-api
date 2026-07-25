<?php
// محول استجابة بيانات مصادر الرسائل المعتمدة للـ API.

namespace Modules\HwnixCash\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_identifier' => $this->sender_identifier,
            'provider' => $this->provider,
            'is_active' => (bool) $this->is_active,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

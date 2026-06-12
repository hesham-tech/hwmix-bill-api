<?php

namespace Modules\Notification\Http\Resources;

//   مورد بيانات قوالب الإشعارات لتحويل الكيان لاستجابة JSON منسقة وآمنة.

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

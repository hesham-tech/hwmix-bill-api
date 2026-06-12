<?php

namespace Modules\Notification\Http\Resources;

//   مورد بيانات قواعد أتمتة الإشعارات (Workflows) مع خطواتها وقوالبها التابعة لاستجابة JSON منسقة.

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationWorkflowResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'event_type' => $this->event_type,
            'is_active' => (bool) $this->is_active,
            'steps' => $this->steps->map(function ($step) {
                return [
                    'id' => $step->id,
                    'step_number' => $step->step_number,
                    'delay_days' => $step->delay_days,
                    'channel' => $step->channel,
                    'template_id' => $step->template_id,
                    'template_name' => $step->template?->name,
                    'is_active' => (bool) $step->is_active,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace Modules\Notification\Http\Resources;

// تعليق عربي: مورد بيانات إعدادات الواتساب لتحويل النموذج إلى استجابة JSON آمنة مع إخفاء رمز الوصول الحساس (Access Token).

use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppSettingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'phone_number_id' => $this->phone_number_id,
            'waba_id' => $this->waba_id,
            // نتحقق من تهيئة رمز الوصول دون إرجاعه حمايةً للخصوصية والأمان
            'access_token_configured' => !empty($this->access_token),
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

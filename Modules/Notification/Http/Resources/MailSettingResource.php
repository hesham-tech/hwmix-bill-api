<?php

namespace Modules\Notification\Http\Resources;

//   مورد بيانات إعدادات البريد لتحويل الكائن لاستجابة JSON آمنة مع إخفاء كلمة مرور SMTP.

use Illuminate\Http\Resources\Json\JsonResource;

class MailSettingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'mail_transport' => $this->mail_transport,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_username' => $this->mail_username,
            'mail_password_configured' => !empty($this->mail_password),
            'mail_encryption' => $this->mail_encryption,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

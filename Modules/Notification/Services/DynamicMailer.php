<?php

namespace Modules\Notification\Services;

// تعليق عربي: خدمة البريد الإلكتروني الديناميكية لبناء Mailer مخصص لكل شركة في وقت التشغيل.

use Modules\Notification\Models\MailSetting;
use Illuminate\Support\Facades\Mail;

class DynamicMailer
{
    /**
     * الحصول على كائن Mailer مهيأ بإعدادات الشركة المحددة.
     */
    public static function getMailer(MailSetting $setting)
    {
        $mailerName = 'tenant_mailer_' . $setting->company_id;

        // وضع الإعدادات مؤقتاً في إعدادات لارافيل الحالية
        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => $setting->mail_transport ?: 'smtp',
                'host' => $setting->mail_host,
                'port' => $setting->mail_port ?: 587,
                'encryption' => $setting->mail_encryption,
                'username' => $setting->mail_username,
                'password' => $setting->mail_password, // فك التشفير تلقائي
                'timeout' => null,
            ],
            'mail.from' => [
                'address' => $setting->mail_from_address ?: config('mail.from.address'),
                'name' => $setting->mail_from_name ?: config('mail.from.name'),
            ]
        ]);

        return Mail::mailer($mailerName);
    }
}

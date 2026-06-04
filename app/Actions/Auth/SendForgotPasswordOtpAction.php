<?php

/**
 * كلاس مسؤول عن توليد رمز التحقق OTP وحفظه في جدول استعادة البيانات وإرساله للمستخدم بالبريد
 */

namespace App\Actions\Auth;

use App\Models\User;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Models\MailSetting;
use Modules\Notification\Services\DynamicMailer;

class SendForgotPasswordOtpAction
{
    /**
     * تنفيذ عملية توليد وإرسال رمز التحقق
     *
     * @param string $email البريد الإلكتروني للمستلم
     * @return void
     */
    public function execute(string $email, ?string $frontendUrl = null): void
    {
        // 1. جلب المستخدم للوصول لـ company_id الخاص به
        $user = User::withoutGlobalScopes()->where('email', $email)->firstOrFail();

        // 2. توليد رمز OTP عشوائي من 6 أرقام
        $otp = (string) rand(100000, 999999);

        // 3. تحديث أو إدخال الرمز في جدول password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        // 4. إرسال البريد الإلكتروني عبر خادم بريد الشركة النشطة أو خادم النظام الافتراضي
        $mailSetting = MailSetting::where('company_id', $user->company_id)->first();

        if (!$mailSetting || empty($mailSetting->mail_host)) {
            $masterCompanyId = env('SYSTEM_MASTER_COMPANY_ID', 1);
            $mailSetting = MailSetting::where('company_id', $masterCompanyId)->first();
        }

        if ($mailSetting && !empty($mailSetting->mail_host)) {
            // استخدام المايلر الديناميكي الخاص بالشركة
            $mailer = DynamicMailer::getMailer($mailSetting);
            $mailer->to($email)->send(new SendOtpMail($email, $otp, $frontendUrl));
        } else {
            // استخدام خادم البريد الافتراضي للنظام
            Mail::to($email)->send(new SendOtpMail($email, $otp, $frontendUrl));
        }
    }
}

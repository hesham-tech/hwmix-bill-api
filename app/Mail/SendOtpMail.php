<?php

/**
 * كلاس مسؤول عن بناء وإرسال رسالة البريد الإلكتروني المحتوية على كود التحقق OTP ورابط استعادة مباشر
 */

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $email;
    public string $otp;

    public function __construct(string $email, string $otp)
    {
        $this->email = $email;
        $this->otp = $otp;
    }

    public function build()
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $resetLink = $frontendUrl . '/forgot-password?email=' . urlencode($this->email) . '&otp=' . urlencode($this->otp);

        return $this->subject('رمز تحقق ورابط استعادة كلمة المرور')
            ->html("
                <div style='direction: rtl; text-align: right; font-family: sans-serif; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 500px; margin: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                    <h2 style='color: #4f46e5; text-align: center; margin-bottom: 20px;'>استعادة كلمة المرور</h2>
                    <p style='color: #334155; font-size: 15px; line-height: 1.6;'>لقد طلبت إعادة تعيين كلمة المرور الخاصة بك. يمكنك الاختيار بين طريقتين لإكمال العملية:</p>
                    
                    <!-- الخيار الأول: رابط الاستعادة التلقائي المباشر -->
                    <div style='text-align: center; margin: 25px 0 15px;'>
                        <a href='{$resetLink}' style='background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);'>
                            تغيير كلمة المرور مباشرة (نقرة واحدة)
                        </a>
                    </div>
                    
                    <div style='text-align: center; color: #64748b; font-size: 13px; margin-bottom: 25px;'>
                        (سيقوم هذا الزر بنقلك وتهيئة كود التحقق تلقائياً دون كتابته)
                    </div>

                    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;' />

                    <!-- الخيار الثاني: النسخ اليدوي للرمز -->
                    <p style='color: #334155; font-size: 14px;'><strong>أو قم بنسخ كود التحقق (OTP) التالي يدوياً:</strong></p>
                    
                    <div style='background-color: #f8fafc; border: 1px dashed #cbd5e1; padding: 15px; text-align: center; border-radius: 8px; font-size: 26px; font-weight: bold; letter-spacing: 3px; color: #4f46e5; margin: 15px 0; -webkit-user-select: all; user-select: all; cursor: pointer;' title='انقر مرتين لنسخ الرمز'>
                        {$this->otp}
                    </div>

                    <p style='color: #64748b; font-size: 13px; text-align: center; margin-top: 5px;'>
                        (انقر نقرة مزدوجة على الرمز أعلاه لتحديده ونسخه بسهولة)
                    </p>

                    <p style='color: #64748b; font-size: 13px; border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 25px;'>
                        * هذا الرمز والرابط صالحان لمدة 15 دقيقة فقط من وقت الطلب لحماية حسابك.<br>
                        * إذا لم تكن أنت من طلب هذا الإجراء، يرجى تجاهل هذا البريد تماماً.
                    </p>
                </div>
            ");
    }
}

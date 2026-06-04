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
    public ?string $frontendUrl;

    public function __construct(string $email, string $otp, ?string $frontendUrl = null)
    {
        $this->email = $email;
        $this->otp = $otp;
        $this->frontendUrl = $frontendUrl;
    }

    public function build()
    {
        $frontendUrl = $this->frontendUrl ?: env('FRONTEND_URL', 'http://localhost:5173/forgot-password');
        
        if (str_contains($frontendUrl, '?')) {
            $frontendUrl = explode('?', $frontendUrl)[0];
        }
        $frontendUrl = rtrim($frontendUrl, '/');
        
        if (!str_contains($frontendUrl, 'forgot-password')) {
            $frontendUrl .= '/forgot-password';
        }

        $resetLink = $frontendUrl . '?email=' . urlencode($this->email) . '&otp=' . urlencode($this->otp);

        return $this->subject('رمز تحقق استعادة كلمة المرور #' . $this->otp)
            ->html("
                <div style='direction: rtl; text-align: right; font-family: system-ui, -apple-system, sans-serif; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 400px; margin: auto; box-shadow: 0 2px 4px rgba(0,0,0,0.02);'>
                    <h3 style='color: #4f46e5; text-align: center; margin-top: 0; margin-bottom: 12px; font-size: 18px;'>استعادة كلمة المرور</h3>
                    <p style='color: #334155; font-size: 13px; line-height: 1.5; margin-bottom: 15px;'>لقد طلبت إعادة تعيين كلمة المرور الخاصة بك. اختر أحد الخيارين للبدء:</p>
                    
                    <!-- الخيار الأول: رابط الاستعادة التلقائي المباشر -->
                    <div style='text-align: center; margin: 15px 0 8px;'>
                        <a href='{$resetLink}' style='background-color: #4f46e5; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px; display: inline-block;'>
                            تغيير كلمة المرور مباشرة
                        </a>
                    </div>
                    
                    <div style='text-align: center; color: #64748b; font-size: 11px; margin-bottom: 15px;'>
                        (يقوم بتهيئة الرمز تلقائياً دون كتابته)
                    </div>

                    <hr style='border: 0; border-top: 1px solid #f1f5f9; margin: 15px 0;' />

                    <!-- الخيار الثاني: النسخ اليدوي للرمز -->
                    <p style='color: #334155; font-size: 13px; margin: 0 0 8px;'><strong>أو استخدم كود التحقق (OTP) التالي:</strong></p>
                    
                    <div style='background-color: #f8fafc; border: 1px dashed #cbd5e1; padding: 10px; text-align: center; border-radius: 6px; font-size: 22px; font-weight: bold; letter-spacing: 2px; color: #4f46e5; margin: 10px 0;' title='انقر مرتين لنسخ الرمز'>
                        {$this->otp}
                    </div>

                    <p style='color: #64748b; font-size: 11px; text-align: center; margin-top: 5px; margin-bottom: 15px;'>
                        (انقر نقرة مزدوجة لتحديده ونسخه)
                    </p>

                    <div style='color: #64748b; font-size: 11px; border-top: 1px solid #f1f5f9; padding-top: 10px; margin-top: 15px; line-height: 1.4;'>
                        * الرمز والرابط صالحان لمدة 15 دقيقة فقط.<br>
                        * إذا لم تكن أنت من طلب هذا، يرجى تجاهل البريد.
                    </div>
                </div>
            ");
    }
}

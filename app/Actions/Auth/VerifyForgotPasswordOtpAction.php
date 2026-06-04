<?php

/**
 * كلاس مسؤول عن التحقق من صحة وصلاحية رمز الـ OTP في الخلفية
 */

namespace App\Actions\Auth;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VerifyForgotPasswordOtpAction
{
    /**
     * التحقق من صحة وصلاحية الرمز
     *
     * @param string $email البريد الإلكتروني
     * @param string $otp الرمز المدخل
     * @return bool
     * @throws \Exception
     */
    public function execute(string $email, string $otp): bool
    {
        // 1. البحث عن الرمز
        $resetToken = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$resetToken) {
            throw new \Exception('لم يتم طلب رمز تحقق لهذا البريد الإلكتروني.');
        }

        // 2. التحقق من تطابق الرمز
        if ($resetToken->token !== $otp) {
            throw new \Exception('رمز التحقق (OTP) المدخل غير صحيح.');
        }

        // 3. التحقق من صلاحية الوقت (ألا يتجاوز 15 دقيقة)
        $createdAt = Carbon::parse($resetToken->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            throw new \Exception('انتهت صلاحية رمز التحقق (OTP).');
        }

        return true;
    }
}

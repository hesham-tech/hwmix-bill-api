<?php

/**
 * كلاس مسؤول عن التحقق من رمز OTP المدخل وتحديث كلمة مرور المستخدم وحذف الرمز المستخدم
 */

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ResetPasswordAction
{
    /**
     * تنفيذ عملية التحقق وتحديث كلمة المرور
     *
     * @param string $email البريد الإلكتروني للمستلم
     * @param string $otp رمز التحقق المدخل
     * @param string $password كلمة المرور الجديدة
     * @return void
     * @throws \Exception
     */
    public function execute(string $email, string $otp, string $password): void
    {
        // 1. البحث عن رمز التحقق المرتبط بالبريد الإلكتروني
        $resetToken = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$resetToken) {
            throw new \Exception('لم يتم طلب رمز تحقق لهذا البريد الإلكتروني أو الرمز غير موجود.');
        }

        // 2. التحقق من تطابق الرمز
        if ($resetToken->token !== $otp) {
            throw new \Exception('رمز التحقق (OTP) المدخل غير صحيح.');
        }

        // 3. التحقق من صلاحية الوقت (ألا يتجاوز 15 دقيقة)
        $createdAt = Carbon::parse($resetToken->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            // حذف الرمز المنتهي الصلاحية تلقائياً لتنظيف الجدول
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw new \Exception('انتهت صلاحية رمز التحقق (OTP)، يرجى طلب رمز جديد.');
        }

        // 4. تحديث كلمة المرور للمستخدم (تجاوز الفلتر العالمي للشركة لضمان تحديث الحساب بشكل كامل)
        $user = User::withoutGlobalScopes()->where('email', $email)->firstOrFail();
        
        DB::transaction(function () use ($user, $password, $email) {
            $user->update([
                'password' => Hash::make($password),
            ]);

            // 5. مسح رمز التحقق لمنع إعادة استخدامه
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });
    }
}

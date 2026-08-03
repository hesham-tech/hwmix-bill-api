<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

/**
 * يحدد سياق الشركة النشطة بناءً على مصدر الطلب (Mobile / Web).
 */
class CurrentCompanyResolver
{
    /**
     * إرجاع معرف الشركة النشطة.
     *
     * @return int|null
     */
    public function resolve(): ?int
    {
        $request = request();

        // 1. فحص هل الطلب من تطبيق الموبايل ويحتوي على الهيدر المخصص للشركة
        if ($request && $request->hasHeader('X-HWNIX-COMPANY')) {
            $companyId = $request->header('X-HWNIX-COMPANY');
            if ($companyId) {
                return (int) $companyId;
            }
        }

        // 2. إذا لم يكن موبايل، ارجع الشركة النشطة للمستخدم في الويب
        $user = Auth::user();
        if ($user) {
            return $user->active_company_id;
        }

        return null;
    }
}

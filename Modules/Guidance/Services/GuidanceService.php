<?php

namespace Modules\Guidance\Services;

/**
 * خدمة إدارة تقدم إرشادات وجولات المستخدم (Business Logic) وتتبعها لكل شركة.
 */

use Modules\Guidance\Models\UserGuidanceProgress;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GuidanceService
{
    /**
     * جلب قائمة بخطوات التقدم للمستخدم الحالي داخل الشركة الحالية.
     */
    public function getProgressForUser(User $user, int $companyId)
    {
        return UserGuidanceProgress::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->get();
    }

    /**
     * تسجيل خطوة أو جولة إرشادية كمنتهية أو متخطاة.
     */
    public function completeStep(User $user, int $companyId, string $key, bool $skipped = false): UserGuidanceProgress
    {
        return DB::transaction(function () use ($user, $companyId, $key, $skipped) {
            return UserGuidanceProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'key' => $key,
                ],
                [
                    'completed_at' => Carbon::now(),
                    'skipped' => $skipped,
                    'created_by' => $user->id, // يتم تعيينه تلقائياً بواسطة Blameable ولكن للتأكيد الإضافي
                ]
            );
        });
    }

    /**
     * إلغاء وسم خطوة إرشادية كمنتهية (حذف السجل).
     */
    public function uncompleteStep(User $user, int $companyId, string $key): bool
    {
        return DB::transaction(function () use ($user, $companyId, $key) {
            return UserGuidanceProgress::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->delete() > 0;
        });
    }

    /**
     * إعادة تعيين وحذف جميع تقدم الإرشادات للمستخدم في الشركة المحددة (مثلاً عند الرغبة في إعادة عرض الجولات).
     */
    public function resetProgressForUser(User $user, int $companyId): bool
    {
        return DB::transaction(function () use ($user, $companyId) {
            return UserGuidanceProgress::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->delete() >= 0;
        });
    }
}

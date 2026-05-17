<?php

/**
 * كلاس مسؤول عن تسجيل مستخدم داخلي للشركة
 * هذا الكلاس يقوم بالبحث عن المستخدم عالمياً وربطه بالشركة الحالية
 */

namespace App\Actions\User;

use App\Models\User;
use App\Models\CompanyUser;
use Modules\Companies\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Throwable;

class RegisterInternalUserAction
{
    /**
     * تنفيذ عملية التسجيل
     *
     * @param array $data بيانات المستخدم
     * @param int|null $companyId معرف الشركة (اختياري، يستخدم الشركة النشطة إذا لم يحدد)
     * @param User|null $creatorUser المستخدم الذي يقوم بالعملية
     * @return CompanyUser
     * @throws Throwable
     */
    public function execute(array $data, ?int $companyId = null, ?User $creatorUser = null): CompanyUser
    {
        $creatorUser = $creatorUser ?? Auth::user();
        $companyId = $companyId ?? $creatorUser->company_id;

        return DB::transaction(function () use ($data, $companyId, $creatorUser) {
            // 1. البحث عن مستخدم موجود مسبقاً عالمياً
            $user = User::withoutGlobalScopes()
                ->where(function ($query) use ($data) {
                    $query->where('phone', $data['phone']);
                    if (!empty($data['email'])) {
                        $query->orWhere('email', $data['email']);
                    }
                })->first();

            if ($user) {
                // التحقق من الارتباط المسبق بالشركة
                $companyUser = CompanyUser::where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->first();

                if ($companyUser) {
                    throw new \Exception('هذا المستخدم مرتبط مسبقاً بهذه الشركة.');
                }
            } else {
                // 2. إنشاء مستخدم عالمي جديد
                $user = User::create([
                    'username' => $data['username'] ?? $data['phone'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'],
                    'password' => $data['password'] ?? 'password',
                    'created_by' => $creatorUser->id,
                    'active_company_id' => $companyId,
                    'full_name' => $data['full_name'],
                    'nickname' => $data['nickname'],
                ]);
            }

            // 3. إنشاء سجل العلاقة مع الشركة
            $companyUser = CompanyUser::create([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'nickname_in_company' => $data['nickname'] ?? $user->nickname,
                'full_name_in_company' => $data['full_name'] ?? $user->full_name,
                'customer_type_in_company' => $data['customer_type'] ?? 'default',
                'status' => $data['status'] ?? 'active',
                'created_by' => $creatorUser->id,
            ]);

            // 4. معالجة الرصيد الابتدائي (إن وجد)
            if (isset($data['balance']) && $data['balance'] != 0) {
                if ($data['balance'] > 0) {
                    $user->deposit($data['balance']);
                } else {
                    $user->withdraw(abs($data['balance']));
                }
            }

            // 5. مزامنة الفروع وتحديد الفرع المستهدف للخزنة
            $targetBranchId = null;
            if (isset($data['branch_ids'])) {
                $branchIds = array_filter((array) $data['branch_ids']);
                $validBranchIds = Branch::whereIn('id', $branchIds)
                    ->where('company_id', $companyId)
                    ->pluck('id')->toArray();
                $user->branches()->syncWithoutDetaching($validBranchIds);

                if (!empty($validBranchIds)) {
                    $targetBranchId = $validBranchIds[0];
                }
            }

            if (!$targetBranchId) {
                // إذا لم يتم إرسال فرع، نتحقق من الفرع النشط للجلسة أو فرع المنشئ، ثم كخيار بديل الفرع الافتراضي للشركة
                $sessionBranchId = config('app.active_branch_id') ?? ($creatorUser ? $creatorUser->branch_id : null);

                if ($sessionBranchId) {
                    $targetBranchId = $sessionBranchId;
                } else {
                    $defaultBranch = Branch::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('is_default', true)
                        ->first();
                    $targetBranchId = $defaultBranch ? $defaultBranch->id : null;
                }

                // ربط المستخدم بالفرع تلقائياً إن لم يرسل أي فرع لضمان سلامة السياق المالي والتشغيلي
                if ($targetBranchId) {
                    $user->branches()->syncWithoutDetaching([$targetBranchId]);
                }
            }

            // إنشاء/تحديث الخزنة الافتراضية للمستخدم لتكون مرتبطة بالفرع المستهدف فوراً
            try {
                app(\App\Services\CashBoxService::class)->createDefaultCashBoxForUserCompany(
                    $user->id,
                    $companyId,
                    $creatorUser->id,
                    $targetBranchId
                );
            } catch (\Exception $e) {
                Log::error("فشل إنشاء/تحديث الخزنة للفرع المستهدف للمستخدم {$user->id}: " . $e->getMessage());
            }

            // 6. مزامنة الصور
            if (isset($data['images_ids'])) {
                $user->syncImages($data['images_ids'], 'avatar');
            }

            // 7. الأدوار والصلاحيات (سياق الشركة)
            $this->syncPermissions($user, $data, $companyId, $creatorUser);

            return $companyUser->load('user', 'company');
        });
    }

    /**
     * مزامنة الصلاحيات والأدوار داخل سياق الشركة
     */
    protected function syncPermissions(User $user, array $data, int $companyId, User $creatorUser): void
    {
        $originalTeamId = getPermissionsTeamId();
        setPermissionsTeamId($companyId);

        $isSuperAdmin = $creatorUser->can(perm_key('admin.super'));

        if (isset($data['roles'])) {
            $roles = (array) $data['roles'];
            if (!$isSuperAdmin) {
                $myRoles = $creatorUser->getRoleNames()->toArray();
                $roles = array_intersect($roles, $myRoles);
            }
            $user->syncRoles($roles);
        }

        if (isset($data['permissions'])) {
            $permissions = (array) $data['permissions'];
            if (!$isSuperAdmin) {
                $myPermissions = $creatorUser->getAllPermissions()->pluck('name')->toArray();
                $permissions = array_intersect($permissions, $myPermissions);
            }
            $user->syncPermissions($permissions);
        }

        setPermissionsTeamId($originalTeamId);
    }
}

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

            // 4. مزامنة العلاقات التجارية (Business Relations)
            $relationTypes = $data['relation_types'] ?? [];
            if (empty($relationTypes)) {
                // تراجع تلقائي للتوافقية
                if (isset($data['roles']) && !empty($data['roles'])) {
                    $relationTypes[] = 'employee';
                } else {
                    $relationTypes[] = 'customer';
                }
            }

            $userRelations = [];
            foreach (array_unique($relationTypes) as $type) {
                \Modules\Companies\Models\BusinessRelation::firstOrCreate([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'relation_type' => $type,
                ]);
                $userRelations[] = $type;
            }
            // حذف أي علاقات قديمة غير محددة
            \Modules\Companies\Models\BusinessRelation::where([
                'company_id' => $companyId,
                'user_id' => $user->id,
            ])->whereNotIn('relation_type', $userRelations)->delete();

            // 5. معالجة الرصيد الابتدائي (الافتتاحي للذمم الدفترية)
            $startingBalances = $data['starting_balances'] ?? [];

            // توافقية خلفية مع الحقل الموحد القديم (balance)
            if (empty($startingBalances) && isset($data['balance']) && $data['balance'] != 0) {
                $val = (float)$data['balance'];
                if (in_array('supplier', $relationTypes)) {
                    $startingBalances['payable'] = $val;
                } elseif (in_array('customer', $relationTypes)) {
                    $startingBalances['receivable'] = $val;
                }
            }

            foreach ($startingBalances as $relType => $amount) {
                if ($amount == 0) continue;

                // تحديث أو إنشاء سجل رصيد الطرف المالي
                $balModel = \Modules\Companies\Models\StakeholderFinancialBalance::firstOrCreate([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'relation_type' => $relType,
                ]);

                $balModel->balance = (float)$amount;
                $balModel->save();

                // تسجيل قيد افتتاحي دفتري (غير نقدي)
                \App\Models\Transaction::create([
                    'user_id' => $user->id,
                    'cashbox_id' => null, // حركة ذمم دفترية
                    'company_id' => $companyId,
                    'type' => 'deposit',
                    'amount' => (float)$amount,
                    'balance_before' => 0.00,
                    'balance_after' => (float)$amount,
                    'created_by' => $creatorUser->id,
                    'description' => 'رصيد افتتاحي للنظام - ' . ($relType === 'receivable' ? 'ذمة مدينة عميل' : 'ذمة دائنة مورد'),
                ]);
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

            // إذا كان الموظف لديه رصيد نقدي عهدة ابتدائي
            if (in_array('employee', $relationTypes) && isset($data['balance']) && $data['balance'] != 0) {
                $defaultBox = \App\Models\CashBox::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->first();
                if ($defaultBox) {
                    $defaultBox->balance = (float)$data['balance'];
                    $defaultBox->save();
                }
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

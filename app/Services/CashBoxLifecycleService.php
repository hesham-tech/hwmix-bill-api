<?php

namespace App\Services;

use App\Models\User;
use App\Enums\CashBoxStatus;
use App\Services\CashBoxDomainRules;
use App\Events\CashBoxCreated;
use App\Events\CashBoxActivated;
use App\Events\CashBoxDeactivated;
use App\Events\CashBoxArchived;
use App\Events\DefaultCashBoxChanged;
use App\Events\CashBoxAccessGranted;
use App\Events\CashBoxAccessRevoked;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * خدمة إدارة دورة حياة الخزن وآلة الحالات الانتقالية والتحكم بالصلاحيات (CashBox Lifecycle Service)
 */
class CashBoxLifecycleService
{
    protected CashBoxDomainRules $rules;

    public function __construct(CashBoxDomainRules $rules)
    {
        $this->rules = $rules;
    }

    /**
     * إنشاء خزنة جديدة بعد التحقق من شروط النطاق
     */
    public function create(array $data, ?User $actor = null)
    {
        $this->rules->validateCreationOrUpdate($data);

        return DB::transaction(function () use ($data, $actor) {
            // توليد الرمز تلقائياً CBX-000000
            $latest = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->orderBy('id', 'desc')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            
            $data['code'] = 'CBX-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

            // خارطة حقل النشاط للحالة
            if (!isset($data['status']) && isset($data['is_active'])) {
                $data['status'] = $data['is_active'] ? CashBoxStatus::ACTIVE->value : CashBoxStatus::INACTIVE->value;
            }
            $data['status'] = $data['status'] ?? CashBoxStatus::ACTIVE->value;

            // تحديد فئة الموديل للتوافق
            $modelClass = isset($data['cash_box_type_id']) ? \Modules\Accounting\Models\CashBox::class : \App\Models\CashBox::class;
            $cashBox = $modelClass::create($data);

            event(new CashBoxCreated($cashBox, $actor));

            return $cashBox;
        });
    }

    /**
     * تنشيط الخزينة (التحقق من الانتقال والـ Invariants)
     */
    public function activate($cashBox, ?User $actor = null): void
    {
        $this->rules->validateTransition($cashBox, CashBoxStatus::ACTIVE);

        DB::transaction(function () use ($cashBox, $actor) {
            $cashBox->status = CashBoxStatus::ACTIVE;
            $cashBox->save();

            event(new CashBoxActivated($cashBox, $actor));
        });
    }

    /**
     * تعطيل الخزينة مؤقتاً
     */
    public function deactivate($cashBox, ?User $actor = null): void
    {
        $this->rules->validateTransition($cashBox, CashBoxStatus::INACTIVE);

        DB::transaction(function () use ($cashBox, $actor) {
            $cashBox->status = CashBoxStatus::INACTIVE;
            $cashBox->save();

            // فحص سحب الخزنة الافتراضية إذا تم تعطيلها من علاقات الفروع
            $memberships = DB::table('branch_user')->where('default_cash_box_id', $cashBox->id)->get();
            foreach ($memberships as $membership) {
                $user = User::withoutGlobalScopes()->find($membership->user_id);
                if ($user) {
                    $this->changeDefault($user, null, $actor, $membership->branch_id);
                }
            }

            event(new CashBoxDeactivated($cashBox, $actor));
        });
    }

    /**
     * أرشفة الخزينة نهائياً (Legacy)
     */
    public function archive($cashBox, ?User $actor = null): void
    {
        $this->rules->validateTransition($cashBox, CashBoxStatus::ARCHIVED);

        DB::transaction(function () use ($cashBox, $actor) {
            $cashBox->status = CashBoxStatus::ARCHIVED;
            $cashBox->save();

            // إزالة تعيينها كخزنة افتراضية لأي مستخدم من علاقات الفروع
            $memberships = DB::table('branch_user')->where('default_cash_box_id', $cashBox->id)->get();
            foreach ($memberships as $membership) {
                $user = User::withoutGlobalScopes()->find($membership->user_id);
                if ($user) {
                    $this->changeDefault($user, null, $actor, $membership->branch_id);
                }
            }

            event(new CashBoxArchived($cashBox, $actor));
        });
    }

    /**
     * تغيير المسؤول / المالك عن العهدة
     */
    public function assignOwner($cashBox, ?int $userId, ?User $actor = null): void
    {
        DB::transaction(function () use ($cashBox, $userId, $actor) {
            $oldData = $cashBox->toArray();
            
            $newData = array_merge($cashBox->toArray(), [
                'user_id' => $userId,
                'access_type' => $userId ? 'personal' : 'company_shared'
            ]);

            $this->rules->validateCreationOrUpdate($newData);

            $cashBox->user_id = $userId;
            $cashBox->access_type = $userId ? 'personal' : 'company_shared';
            $cashBox->save();

            // إذا أصبحت مشتركة، نقوم بإلغاء ارتباطها كافتراضية للمستخدم القديم في هذا الفرع
            if (is_null($userId) && $oldData['user_id']) {
                $oldUser = User::withoutGlobalScopes()->find($oldData['user_id']);
                if ($oldUser) {
                    $membership = $oldUser->branchMembership($cashBox->branch_id);
                    if ($membership && $membership->default_cash_box_id === $cashBox->id) {
                        $this->changeDefault($oldUser, null, $actor, $cashBox->branch_id);
                    }
                }
            }
        });
    }

    /**
     * تحويل الخزينة لتصبح مشتركة
     */
    public function convertToShared($cashBox, ?User $actor = null): void
    {
        $this->assignOwner($cashBox, null, $actor);
    }

    /**
     * تحويل الخزينة لتصبح شخصية
     */
    public function convertToPersonal($cashBox, int $userId, ?User $actor = null): void
    {
        $this->assignOwner($cashBox, $userId, $actor);
    }

    /**
     * تغيير الخزنة الافتراضية المفضلة للمستخدم
     */
    public function changeDefault(User $user, ?int $cashBoxId, ?User $actor = null, ?int $branchId = null): void
    {
        $cashBox = null;
        if ($cashBoxId) {
            $cashBox = \Modules\Accounting\Models\CashBox::withoutGlobalScopes()->find($cashBoxId);
            if (!$cashBox) {
                $cashBox = \App\Models\CashBox::withoutGlobalScopes()->findOrFail($cashBoxId);
            }
            if (!$user->canAccessCashBox($cashBox)) {
                throw new Exception("لا يمكن تعيين الخزينة كخيار افتراضي لأن المستخدم لا يملك صلاحيات وصول إليها.");
            }
            if ($cashBox->status !== CashBoxStatus::ACTIVE) {
                throw new Exception("لا يمكن تعيين الخزينة كخيار افتراضي لأنها ليست نشطة.");
            }
        }

        $targetBranchId = $branchId ?? ($cashBox ? $cashBox->branch_id : $user->branch_id);

        if (!$targetBranchId) {
            throw new Exception("تعذر تحديد الفرع لتعيين الخزينة الافتراضية.");
        }

        // جلب المعرف القديم لتمريره لحدث التغيير
        $oldId = null;
        $membership = $user->branchMembership($targetBranchId);
        if ($membership) {
            $oldId = $membership->default_cash_box_id;
        }

        if ($oldId === $cashBoxId) {
            return;
        }

        DB::transaction(function () use ($user, $cashBoxId, $cashBox, $oldId, $actor, $targetBranchId) {
            // تحديث أو إنشاء علاقة العضوية بالفرع
            DB::table('branch_user')->updateOrInsert(
                ['user_id' => $user->id, 'branch_id' => $targetBranchId],
                [
                    'default_cash_box_id' => $cashBoxId,
                    'updated_at' => now()
                ]
            );

            // الحفاظ على توافق الحقل القديم مؤقتاً للتوافق للخلف
            if ($user->branch_id == $targetBranchId) {
                $user->default_cash_box_id = $cashBoxId;
                $user->save();
            }

            event(new DefaultCashBoxChanged($cashBox, $user, $oldId, $cashBoxId, $actor));
        });
    }

    /**
     * منح صلاحية لمستخدم على خزنة مشتركة
     */
    public function grantAccess($cashBox, int $userId, ?User $actor = null): void
    {
        if ($cashBox->access_type !== 'company_shared') {
            throw new Exception("لا يمكن منح صلاحية وصول إضافية على الخزن الشخصية.");
        }

        $user = User::withoutGlobalScopes()->findOrFail($userId);

        DB::transaction(function () use ($cashBox, $user, $actor) {
            if (!$cashBox->users()->where('users.id', $user->id)->exists()) {
                $cashBox->users()->attach($user->id);
                event(new CashBoxAccessGranted($cashBox, $user, $actor));
            }
        });
    }

    /**
     * سحب صلاحية من مستخدم على خزنة مشتركة
     */
    public function revokeAccess($cashBox, int $userId, ?User $actor = null): void
    {
        $user = User::withoutGlobalScopes()->findOrFail($userId);

        DB::transaction(function () use ($cashBox, $user, $actor) {
            if ($cashBox->users()->where('users.id', $user->id)->exists()) {
                $cashBox->users()->detach($user->id);

                // إزالة الافتراضية إذا كانت هي الخزينة المفضلة لديه في هذا الفرع
                $membership = $user->branchMembership($cashBox->branch_id);
                if ($membership && $membership->default_cash_box_id === $cashBox->id) {
                    $this->changeDefault($user, null, $actor, $cashBox->branch_id);
                }

                event(new CashBoxAccessRevoked($cashBox, $user, $actor));
            }
        });
    }
}

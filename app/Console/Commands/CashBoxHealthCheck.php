<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Models\CashBox;
use App\Models\User;
use App\Enums\CashBoxStatus;
use Illuminate\Support\Facades\DB;

class CashBoxHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cashboxes:health-check';

    /**
     * The console command description.
     */
    protected $description = 'فحص السلامة المعمارية للخزن وقواعد العمل الثابتة في قاعدة البيانات (Architecture Integrity Check)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 بدء فحص السلامة المعمارية لنظام الخزن...');
        $errors = 0;

        // 1. فحص وجود أكثر من افتراضية لنفس المستخدم في نفس الشركة (في الجدول القديم/الوهمي للتوافق)
        $duplicateDefaults = CashBox::withoutGlobalScopes()
            ->select('user_id', 'company_id')
            ->whereNotNull('user_id')
            ->where('is_default', true)
            ->groupBy('user_id', 'company_id')
            ->having(DB::raw('count(*)'), '>', 1)
            ->get();

        if ($duplicateDefaults->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: يوجد مستخدم يملك أكثر من خزنة افتراضية بجدول cash_boxes لشركة واحدة!');
            foreach ($duplicateDefaults as $item) {
                $this->line("   - مستخدم ID: {$item->user_id}، شركة ID: {$item->company_id}");
            }
        }

        // 2. فحص وجود خزن مشتركة ولكن لها user_id
        $sharedWithUser = CashBox::withoutGlobalScopes()
            ->where('access_type', 'company_shared')
            ->whereNotNull('user_id')
            ->get();

        if ($sharedWithUser->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: خزن مشتركة (company_shared) تحتوي على معرف مستخدم (user_id)!');
            foreach ($sharedWithUser as $box) {
                $this->line("   - خزنة ID: {$box->id}، الاسم: {$box->name}، مستخدم: {$box->user_id}");
            }
        }

        // 3. فحص وجود خزن شخصية ولكن ليس لها user_id
        $personalWithoutUser = CashBox::withoutGlobalScopes()
            ->where('access_type', 'personal')
            ->whereNull('user_id')
            ->get();

        if ($personalWithoutUser->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: خزن شخصية (personal) لا تحتوي على معرف مستخدم (user_id)!');
            foreach ($personalWithoutUser as $box) {
                $this->line("   - خزنة ID: {$box->id}، الاسم: {$box->name}");
            }
        }

        // 4. فحص وجود افتراضية للمستخدم تشير لخزنة غير نشطة
        $inactiveDefaults = DB::table('branch_user')
            ->whereNotNull('default_cash_box_id')
            ->get()
            ->filter(function($row) {
                $box = CashBox::withoutGlobalScopes()->find($row->default_cash_box_id);
                return $box && $box->status !== CashBoxStatus::ACTIVE;
            });

        if ($inactiveDefaults->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: مستخدمون لديهم خزنة افتراضية غير نشطة في فروعهم!');
            foreach ($inactiveDefaults as $row) {
                $this->line("   - مستخدم ID: {$row->user_id}، فرع ID: {$row->branch_id}، خزنة افتراضية: {$row->default_cash_box_id}");
            }
        }

        // 5. فحص وجود سجلات pivot لمستخدم من شركة أخرى (Cross-Tenant)
        $crossTenantPivot = DB::table('cash_box_user')
            ->join('users', 'cash_box_user.user_id', '=', 'users.id')
            ->join('cash_boxes', 'cash_box_user.cash_box_id', '=', 'cash_boxes.id')
            ->whereColumn('users.active_company_id', '!=', 'cash_boxes.company_id')
            ->select('cash_box_user.user_id', 'cash_box_user.cash_box_id', 'users.active_company_id as user_company', 'cash_boxes.company_id as box_company')
            ->get();

        if ($crossTenantPivot->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: صلاحيات خزن مشتركة (pivot) تعبر الشركات (Cross-Tenant)!');
            foreach ($crossTenantPivot as $pivot) {
                $this->line("   - مستخدم ID: {$pivot->user_id} (شركة {$pivot->user_company})، خزنة ID: {$pivot->cash_box_id} (شركة {$pivot->box_company})");
            }
        }

        // 6. فحص وجود خزنة بدون فرع
        $missingBranch = CashBox::withoutGlobalScopes()->whereNull('branch_id')->get();
        if ($missingBranch->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: خزن غير مرتبطة بأي فرع (branch_id = null)!');
            foreach ($missingBranch as $box) {
                $this->line("   - خزنة ID: {$box->id}، الاسم: {$box->name}");
            }
        }

        // 7. فحص وجود خزنة بدون نوع
        $missingType = CashBox::withoutGlobalScopes()->whereNull('cash_box_type_id')->get();
        if ($missingType->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: خزن غير مرتبطة بنوع خزنة (cash_box_type_id = null)!');
            foreach ($missingType as $box) {
                $this->line("   - خزنة ID: {$box->id}، الاسم: {$box->name}");
            }
        }

        // 8. فحص مستخدم لديه default ولا يملك صلاحية وصول عليها
        $usersNoAccessDefault = DB::table('branch_user')
            ->whereNotNull('default_cash_box_id')
            ->get()
            ->filter(function($row) {
                $user = User::withoutGlobalScopes()->find($row->user_id);
                $box = CashBox::withoutGlobalScopes()->find($row->default_cash_box_id);
                return $user && $box && !$user->canAccessCashBox($box);
            });

        if ($usersNoAccessDefault->isNotEmpty()) {
            $errors++;
            $this->error('❌ خطأ: مستخدمون لديهم خزنة افتراضية لا يملكون صلاحية الوصول إليها في فروعهم!');
            foreach ($usersNoAccessDefault as $row) {
                $user = User::withoutGlobalScopes()->find($row->user_id);
                $this->line("   - مستخدم ID: {$row->user_id}، الاسم: " . ($user ? $user->name : 'N/A') . "، فرع ID: {$row->branch_id}، خزنة: {$row->default_cash_box_id}");
            }
        }

        // 9. مستخدم لديه قدرة العهدة ولا يملك خزنة شخصية
        $usersNeedsCustody = User::withoutGlobalScopes()
            ->get()
            ->filter(function($user) {
                if ($user->active_company_id && $user->hasCapability('has_cash_custody', $user->active_company_id)) {
                    return !CashBox::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('company_id', $user->active_company_id)
                        ->exists();
                }
                return false;
            });

        if ($usersNeedsCustody->isNotEmpty()) {
            $errors++;
            $this->warn('⚠️ تنبيه: مستخدمون لديهم قدرة العهدة (has_cash_custody) ولكن ليس لديهم خزنة شخصية بالشركة!');
            foreach ($usersNeedsCustody as $user) {
                $this->line("   - مستخدم ID: {$user->id}، الاسم: {$user->name}");
            }
        }

        // 10. مستخدم لا يملك قدرة العهدة ولديه خزنة شخصية
        $usersNoCustodyHaveSafe = User::withoutGlobalScopes()
            ->get()
            ->filter(function($user) {
                if ($user->active_company_id && !$user->hasCapability('has_cash_custody', $user->active_company_id)) {
                    return CashBox::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('company_id', $user->active_company_id)
                        ->exists();
                }
                return false;
            });

        if ($usersNoCustodyHaveSafe->isNotEmpty()) {
            $errors++;
            $this->warn('⚠️ تنبيه: مستخدمون لا يملكون قدرة العهدة ولديهم خزنة شخصية بالشركة!');
            foreach ($usersNoCustodyHaveSafe as $user) {
                $this->line("   - مستخدم ID: {$user->id}، الاسم: {$user->name}");
            }
        }

        if ($errors === 0) {
            $this->info('✅ فحص السلامة المعمارية اكتمل بنجاح. لا توجد أي مخالفات للقواعد المعمارية!');
            return 0;
        } else {
            $this->error("🚨 تم العثور على مشكلات في فحص السلامة المعمارية!");
            return 1;
        }
    }
}

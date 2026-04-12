<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\CashBox;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SyncAccountingConvention extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:sync-convention {--dry-run : عرض النتائج بدون تعديل فعلي}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $signature_description = 'تحويل الأرصدة القديمة لمنظومة الأصول والالتزامات الجديدة (قلب الإشارات للعملاء والموردين)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('بدء عملية تحويل الأرصدة للمنظومة الجديدة (+ أصل، - التزام)...');

        DB::transaction(function () use ($dryRun) {
            // 1. معالجة المستخدمين (عملاء وموردين)
            // في المنطق القديم: مديونية العميل كانت سالب، ومديونية المورد كانت موجب.
            // في المنطق الجديد: مديونية العميل موجب، ومديونية المورد سالب.
            // الحل: قلب إشارة كافة المستخدمين (بخلاف الموظفين الذين يملكون عهد نقدية).
            
            // ملاحظة: الموظفين (staff/admin) عهدتهم نقدية، والنقدية دائماً موجب في المنطقتين.
            $users = User::all();

            foreach ($users as $user) {
                // استبعاد الموظفين من قلب الإشارة لأن النقدية (Asset) دائماً موجبة
                if ($user->hasAnyPermission(['admin.page', 'pos.view', 'sales.create'])) {
                    $this->line("تخطي الموظف: {$user->name} (الرصيد: {$user->balance})");
                    continue;
                }

                $oldBalance = (float) $user->balance;
                if ($oldBalance == 0) continue;

                $newBalance = $oldBalance * -1;

                $this->info("تحويل {$user->name}: {$oldBalance} -> {$newBalance}");

                if (!$dryRun) {
                    // 1. تحديث الصناديق التابعة له (المصدر الرئيسي للرصيد)
                    CashBox::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->update([
                            'balance' => DB::raw("balance * -1")
                        ]);
                    
                    // 2. تحديث الرصيد المخزن في الجدول الوسيط (Cache column)
                    DB::table('company_user')
                        ->where('user_id', $user->id)
                        ->update([
                            'balance_in_company' => DB::raw("balance_in_company * -1")
                        ]);
                }
            }

            // 2. تحديث المعاملات التاريخية (اختياري ولكن مهم للتقارير)
            if (!$dryRun) {
                $this->info('تحديث مبالغ المعاملات التاريخية...');
                // قلب إشارة المعاملات التي لا تخص موظفين
                // هذا الجزء قد يكون معقداً، سنكتفي بتنبيه المستخدم.
                $this->warn('لم يتم قلب إشارات المعاملات التاريخية المباشرة (Transactions Table) لتجنب التعارض، الأرصدة الحالية فقط هي ما تم تحديثه.');
            }
        });

        $this->info('تمت العملية بنجاح.');
        if ($dryRun) {
            $this->warn('هذا كان مجرد عرض (Dry Run)، لم يتم تغيير أي بيانات.');
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Installment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCustomerBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:customer-balances {--dry-run : عرض التغييرات دون تنفيذها}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مزامنة أرصدة صناديق العملاء (من ليس لديهم صلاحيات) لتساوي إجمالي الأقساط المتبقية بالسالب';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('بدء عملية مزامنة أرصدة العملاء...');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('!! وضع المحاكاة نشط - لن يتم حفظ أي تغييرات !!');
        }

        // جلب المستخدمين مع أدوارهم وصلاحياتهم وعلاقاتهم بالشركات
        $users = User::with(['roles', 'permissions', 'companyUsers.defaultCashBox'])->get();

        $count = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // شرط العميل: لا يملك أدواراً ولا صلاحيات مباشرة
            if ($user->roles->count() > 0 || $user->permissions->count() > 0) {
                $skipped++;
                continue;
            }

            // جلب كافة الشركات المرتبط بها هذا العميل
            $companies = DB::table('company_user')
                ->where('user_id', $user->id)
                ->pluck('company_id');

            foreach ($companies as $companyId) {
                // جلب الصندوق النقدي الافتراضي لهذا المستخدم في هذه الشركة
                $cashBox = DB::table('cash_boxes')
                    ->where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->first();

                if (!$cashBox) {
                    continue;
                }

                // حساب إجمالي المتبقي من الأقساط غير المسددة لهذا المستخدم في هذه الشركة
                $totalRemaining = DB::table('installments')
                    ->where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
                    ->sum('remaining');

                $newBalance = -abs($totalRemaining);

                if (round($cashBox->balance, 2) != round($newBalance, 2)) {
                    $this->line("مزامنة العميل: {$user->nickname} [ID: {$user->id}, شركة: {$companyId}]");
                    $this->line("   - الرصيد: {$cashBox->balance} -> {$newBalance}");

                    if (!$dryRun) {
                        DB::table('cash_boxes')
                            ->where('id', $cashBox->id)
                            ->update(['balance' => $newBalance]);
                    }
                    $count++;
                }
            }
        }

        if ($dryRun) {
            $this->info("تم فحص البيانات. سيتم تحديث {$count} صندوق عند التنفيذ الفعلي.");
        } else {
            $this->info("اكتملت المزامنة بنجاح. تم تحديث {$count} صندوق نقدي.");
        }

        $this->comment("تجاهل المستخدمين (الموظفين): {$skipped}");
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Invoice;
use App\Models\Installment;
use App\Models\CashBox;
use App\Models\CompanyUser;
use App\Services\CashBoxService;
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
    protected $description = 'تدقيق شامل للبيانات المالية: تصحيح أرصدة الفواتير، الأقساط، إنشاء الخزائن المفقودة، ومزامنة أرصدة العملاء.';

    /**
     * Execute the console command.
     */
    public function handle(CashBoxService $cashBoxService)
    {
        $this->info('--- بدء عملية التدقيق المالي الشامل ---');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('!! وضع المحاكاة نشط !!');
        }

        // 1. تصحيح أرصدة وحالات الأقساط
        $this->info('1. تصحيح الأقساط بناءً على تفاصيل الدفع...');
        $this->auditInstallments($dryRun);

        // 2. تصحيح أرصدة وحالات الفواتير (بناءً على التقسيط أولاً ثم المدفوعات)
        $this->info('2. تصحيح الفواتير بناءً على خطط التقسيط والمدفوعات...');
        $this->auditInvoices($dryRun);

        // 3. ضمان وجود صناديق نقدية لكافة المستخدمين في شركاتهم
        $this->info('3. فحص وإنشاء الصناديق النقدية المفقودة...');
        $this->ensureCashBoxes($cashBoxService, $dryRun);

        // 4. مزامنة أرصدة العملاء (مديونية الأقساط)
        $this->info('4. مزامنة أرصدة العملاء (إجمالي الأقساط المتبقية)...');
        $this->syncUserBalances($dryRun);

        $this->info('--- اكتملت عملية التدقيق والمزامنة بنجاح ---');
    }

    protected function auditInstallments($dryRun)
    {
        $installments = Installment::all();
        $corrected = 0;

        foreach ($installments as $inst) {
            $totalPaid = DB::table('installment_payment_details')
                ->where('installment_id', $inst->id)
                ->sum('amount_paid');

            $calculatedRemaining = round($inst->amount - $totalPaid, 2);
            $newStatus = $inst->status;

            if ($calculatedRemaining <= 0) {
                $newStatus = 'paid';
                $calculatedRemaining = 0;
            } elseif ($calculatedRemaining < $inst->amount) {
                $newStatus = 'partially_paid';
            }

            if (round($inst->remaining, 2) != $calculatedRemaining || $inst->status != $newStatus) {
                if (!$dryRun) {
                    $inst->update([
                        'remaining' => $calculatedRemaining,
                        'status' => $newStatus,
                        'paid_at' => ($newStatus == 'paid' && !$inst->paid_at) ? now() : $inst->paid_at
                    ]);
                }
                $corrected++;
            }
        }
        $this->line("   - تم تصحيح {$corrected} قسط.");
    }

    protected function auditInvoices($dryRun)
    {
        $invoices = Invoice::with('installmentPlan')->get();
        $corrected = 0;

        foreach ($invoices as $inv) {
            $totalPaid = 0;
            $calculatedRemaining = 0;
            $referenceTotal = $inv->net_amount;

            if ($inv->installmentPlan) {
                // إذا كان هناك خطة تقسيط، فالبيانات المرجعية هي الخطة (المحصل الفعلي والمتبقي الفعلي)
                $totalPaid = (float) $inv->installmentPlan->total_collected;
                $calculatedRemaining = (float) $inv->installmentPlan->actual_remaining;
                $referenceTotal = (float) $inv->installmentPlan->total_amount;
            } else {
                // لو كاش عادي، نرجع لسجل المدفوعات
                $totalPaid = (float) DB::table('invoice_payments')
                    ->where('invoice_id', $inv->id)
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $calculatedRemaining = round($inv->net_amount - $totalPaid, 2);
            }

            $newStatus = $inv->status;

            if ($calculatedRemaining <= 0) {
                $newStatus = 'paid';
                $calculatedRemaining = 0;
            } elseif ($calculatedRemaining < $referenceTotal) {
                $newStatus = 'partially_paid';
            } else {
                $newStatus = 'confirmed'; // Unpaid but confirmed
            }

            $needsUpdate = round($inv->paid_amount, 2) != round($totalPaid, 2) ||
                round($inv->remaining_amount, 2) != round($calculatedRemaining, 2) ||
                $inv->status != $newStatus;

            if ($needsUpdate) {
                if (!$dryRun) {
                    $inv->paid_amount = $totalPaid;
                    $inv->remaining_amount = $calculatedRemaining;
                    $inv->status = $newStatus;
                    $inv->save();
                    // تحديث الـ payment_status الداخلي أيضاً
                    if (method_exists($inv, 'updatePaymentStatus')) {
                        $inv->updatePaymentStatus();
                    }
                }
                $corrected++;
            }
        }
        $this->line("   - تم تصحيح {$corrected} فاتورة.");
    }

    protected function ensureCashBoxes($cashBoxService, $dryRun)
    {
        $companyUsers = CompanyUser::all();
        $created = 0;

        foreach ($companyUsers as $cu) {
            $exists = CashBox::where('user_id', $cu->user_id)
                ->where('company_id', $cu->company_id)
                ->where('is_default', true)
                ->exists();

            if (!$exists) {
                if (!$dryRun) {
                    $cashBoxService->createDefaultCashBoxForUserCompany($cu->user_id, $cu->company_id, 1);
                }
                $created++;
            }
        }
        $this->line("   - تم إنشاء {$created} صندوق نقدي مفقود.");
    }

    protected function syncUserBalances($dryRun)
    {
        $users = User::with(['roles', 'permissions'])->get();
        $synced = 0;

        foreach ($users as $user) {
            // مزامنة الجميع (عملاء وموظفين) لضمان اتساق البيانات المالية الشامل

            $cashBoxes = CashBox::where('user_id', $user->id)
                ->where('is_default', true)
                ->get();

            foreach ($cashBoxes as $cb) {
                $totalRemaining = DB::table('installments')
                    ->where('user_id', $user->id)
                    ->where('company_id', $cb->company_id)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['paid', 'تم الدفع', 'canceled', 'cancelled', 'ملغي'])
                    ->sum('remaining');

                $newBalance = -abs($totalRemaining);

                if (round($cb->balance, 2) != round($newBalance, 2)) {
                    if (!$dryRun) {
                        $cb->update(['balance' => $newBalance]);
                    }
                    $synced++;
                }
            }
        }
        $this->line("   - تم تحديث أرصدة {$synced} عميل.");
    }
}

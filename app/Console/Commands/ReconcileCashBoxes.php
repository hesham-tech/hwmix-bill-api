<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CashBox;
use Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReconcileCashBoxes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashbox:reconcile 
                            {--dry-run : عرض الفروقات فقط دون إجراء أي تعديل} 
                            {--fix : إنشاء حركات مالية تسوية لمطابقة المعاملات مع رصيد الخزينة}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مطابقة أرصدة الخزائن مع مجموع الحركات المسجلة في جدول transactions وتسوية الفروقات.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== بدء عملية مطابقة وتدقيق أرصدة الخزائن ===');
        
        $dryRun = $this->option('dry-run') || !$this->option('fix');
        if ($dryRun) {
            $this->warn('!! وضع المعاينة (Dry-Run) نشط: لن يتم تعديل أي بيانات في قاعدة البيانات !!');
        } else {
            $this->warn('!! وضع الإصلاح والتسوية نشط: سيتم إنشاء معاملات تسوية !!');
        }

        $cashBoxes = CashBox::withoutGlobalScopes()->get();
        $discrepanciesCount = 0;
        $fixedCount = 0;

        foreach ($cashBoxes as $cb) {
            // حساب الرصيد بناءً على المعاملات
            $transactions = Transaction::withoutGlobalScopes()
                ->where('cashbox_id', $cb->id)
                ->get();

            $calculatedBalance = 0;
            foreach ($transactions as $t) {
                if (in_array($t->type, ['deposit', 'transfer_in', 'reverse_withdraw', 'refund'])) {
                    $calculatedBalance += (float) $t->amount;
                } elseif (in_array($t->type, ['withdraw', 'transfer_out', 'reverse_deposit', 'payment'])) {
                    $calculatedBalance -= (float) $t->amount;
                }
            }

            $storedBalance = (float) $cb->balance;
            $diff = round($storedBalance - $calculatedBalance, 2);

            if (abs($diff) > 0.01) {
                $discrepanciesCount++;
                $this->line(sprintf(
                    "خزنة #%d (%s) | الشركة: %s | المستخدم: %s",
                    $cb->id,
                    $cb->name,
                    $cb->company_id ?? 'NULL',
                    $cb->user_id ?? 'NULL'
                ));
                $this->line(sprintf(
                    "  -> رصيد مخزن: %.2f | رصيد محسوب: %.2f | الفرق: %.2f",
                    $storedBalance,
                    $calculatedBalance,
                    $diff
                ));

                if (!$dryRun) {
                    DB::transaction(function () use ($cb, $diff, $storedBalance, $calculatedBalance) {
                        $type = $diff > 0 ? 'deposit' : 'withdraw';
                        $amount = abs($diff);
                        
                        Transaction::create([
                            'user_id' => $cb->user_id,
                            'cashbox_id' => $cb->id,
                            'created_by' => $cb->user_id ?? 1,
                            'company_id' => $cb->company_id,
                            'branch_id' => $cb->branch_id,
                            'type' => $type,
                            'amount' => $amount,
                            'balance_before' => $calculatedBalance,
                            'balance_after' => $storedBalance,
                            'description' => 'قيد تسوية تلقائي لمطابقة رصيد الخزينة المخزن',
                        ]);
                    });
                    $this->info("  [تم الإصلاح] تم تسجيل حركة تسوية بالفرق ($diff).");
                    $fixedCount++;
                }
                $this->line('----------------------------------------');
            }
        }

        $this->info(sprintf(
            "=== انتهاء العملية === \nإجمالي الخزائن ذات الفروقات: %d \nتم تسوية وإصلاح: %d",
            $discrepanciesCount,
            $fixedCount
        ));
    }
}

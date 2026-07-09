<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\User;
use App\Models\CashBox;
use App\Models\Transaction;
use Modules\Sales\Models\Invoice;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Illuminate\Support\Facades\DB;

/**
 * مراجعة وتدقيق أرصدة الخزائن وأرصدة الأطراف والتحقق من سلامة العلاقات والتكامل المالي بالكامل.
 */
class AuditStakeholderBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financial:audit-balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مراجعة وتدقيق أرصدة الخزائن وأرصدة الأطراف ومقارنتها بسجل الحركات والمعاملات للتحقق من سلامة البيانات والتكامل المالي (Financial Health Check).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== بدء عملية التدقيق المالي الشامل وفحص سلامة البيانات (Financial Health Check) ===');
        $companies = Company::all();
        $discrepancyCount = 0;

        foreach ($companies as $company) {
            $this->info("--------------------------------------------------");
            $this->info("تدقيق الشركة: {$company->name} (#{$company->id})");

            // 1. تدقيق الخزائن وتطابق حركتها النقدية
            $cashBoxes = CashBox::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->get();

            foreach ($cashBoxes as $box) {
                $txSum = Transaction::withoutGlobalScopes()
                    ->where('cashbox_id', $box->id)
                    ->get()
                    ->reduce(function ($carry, $t) {
                        return in_array($t->type, ['deposit', 'transfer_in', 'reverse_withdraw']) 
                            ? $carry + (float)$t->amount 
                            : $carry - (float)$t->amount;
                    }, 0.00);

                $currentBalance = (float)$box->balance;
                $diff = abs($currentBalance - $txSum);

                if ($diff > 0.01) {
                    $this->error(sprintf(
                        "⚠️ خلل في الخزنة: %s (#%d) | رصيد الخزنة: %.2f | مجموع المعاملات: %.2f | الفرق: %.2f",
                        $box->name,
                        $box->id,
                        $currentBalance,
                        $txSum,
                        $diff
                    ));
                    $discrepancyCount++;
                }
            }

            // 2. تدقيق أرصدة الأطراف وتطابق مديونياتها التاريخية
            $balances = StakeholderFinancialBalance::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->get();

            foreach ($balances as $bal) {
                $user = User::withoutGlobalScopes()->find($bal->user_id);
                if (!$user) {
                    $this->error(sprintf(
                        "⚠️ أرصدة أطراف يتيمة: ميزان أطراف #%d يشير لمستخدم غير موجود (#%d)",
                        $bal->id,
                        $bal->user_id
                    ));
                    $discrepancyCount++;
                    continue;
                }

                // احتساب الرصيد التراكمي للطرف من واقع سجلات الحركات (Transactions Ledger)
                $calculated = 0.00;
                $txs = Transaction::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('user_id', $bal->user_id)
                    ->get();

                foreach ($txs as $tx) {
                    if ($bal->relation_type === 'receivable') {
                        if ($tx->cashbox_id === null) {
                            if ($tx->type === 'deposit') {
                                $calculated += (float)$tx->amount; // إنشاء مديونية
                            } elseif ($tx->type === 'withdraw') {
                                $calculated -= (float)$tx->amount; // إلغاء مديونية
                            }
                        } else {
                            if ($tx->type === 'deposit') {
                                $calculated -= (float)$tx->amount; // سداد من العميل
                            } elseif ($tx->type === 'withdraw') {
                                $calculated += (float)$tx->amount; // مسترد للعميل
                            }
                        }
                    } elseif ($bal->relation_type === 'payable') {
                        if ($tx->cashbox_id === null) {
                            if ($tx->type === 'deposit') {
                                $calculated += (float)$tx->amount; // إنشاء التزام
                            } elseif ($tx->type === 'withdraw') {
                                $calculated -= (float)$tx->amount; // إلغاء/سداد التزام
                            }
                        } else {
                            if ($tx->type === 'withdraw') {
                                $calculated -= (float)$tx->amount; // سداد للمورد
                            } elseif ($tx->type === 'deposit') {
                                $calculated += (float)$tx->amount; // مسترد من المورد
                            }
                        }
                    }
                }

                $diff = abs((float)$bal->balance - $calculated);
                if ($diff > 0.01) {
                    $this->error(sprintf(
                        "⚠️ خلل في رصيد الطرف: %s (#%d) | نوع العلاقة: %s | الرصيد المسجل: %.2f | الرصيد المحسوب: %.2f | الفرق: %.2f",
                        $user->name,
                        $user->id,
                        $bal->relation_type,
                        (float)$bal->balance,
                        $calculated,
                        $diff
                    ));
                    $discrepancyCount++;
                }
            }

            // 3. فحص الفواتير المعلقة بأطراف غير صالحة أو وهمية
            $invalidInvoices = Invoice::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereNotNull('user_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'invoices.user_id');
                })
                ->get();

            foreach ($invalidInvoices as $inv) {
                $this->error(sprintf(
                    "⚠️ فاتورة وهمية: فاتورة رقم %s (#%d) تشير لمستخدم غير موجود (#%d)",
                    $inv->invoice_number,
                    $inv->id,
                    $inv->user_id
                ));
                $discrepancyCount++;
            }
        }

        $this->info("--------------------------------------------------");
        $this->info("=== بدء فحوصات سلامة العلاقات العامة (General Integrity Auditing) ===");

        // 4. فحص الحركات اليتيمة (Transactions orphan checking)
        $orphanTxsUser = Transaction::withoutGlobalScopes()
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'transactions.user_id');
            })
            ->get();

        foreach ($orphanTxsUser as $tx) {
            $this->error(sprintf("⚠️ حركة يتيمة: معاملة #%d تشير لمستخدم غير موجود (#%d)", $tx->id, $tx->user_id));
            $discrepancyCount++;
        }

        $orphanTxsBox = Transaction::withoutGlobalScopes()
            ->whereNotNull('cashbox_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('cash_boxes')
                    ->whereColumn('cash_boxes.id', 'transactions.cashbox_id');
            })
            ->get();

        foreach ($orphanTxsBox as $tx) {
            $this->error(sprintf("⚠️ حركة يتيمة: معاملة #%d تشير لخزنة غير موجودة (#%d)", $tx->id, $tx->cashbox_id));
            $discrepancyCount++;
        }

        // 5. فحص الخزائن بدون شركات صالحة
        $orphanBoxes = CashBox::withoutGlobalScopes()
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('companies')
                    ->whereColumn('companies.id', 'cash_boxes.company_id');
            })
            ->get();

        foreach ($orphanBoxes as $box) {
            $this->error(sprintf("⚠️ خزنة بلا شركة: خزنة #%d (%s) تشير لشركة غير موجودة (#%d)", $box->id, $box->name, $box->company_id));
            $discrepancyCount++;
        }

        // 6. فحص الحركات المعلقة بمصادر فواتير غير صالحة
        $orphanTxsInvoice = Transaction::withoutGlobalScopes()
            ->whereNotNull('source_invoice_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.id', 'transactions.source_invoice_id');
            })
            ->get();

        foreach ($orphanTxsInvoice as $tx) {
            $this->error(sprintf("⚠️ حركة بلا فاتورة: معاملة #%d تشير لفاتورة مصدر غير موجودة (#%d)", $tx->id, $tx->source_invoice_id));
            $discrepancyCount++;
        }

        $this->info("--------------------------------------------------");
        if ($discrepancyCount === 0) {
            $this->info('✅ تم فحص وتدقيق النظام بالكامل: لا توجد أي معاملات يتيمة، خزائن تالفة، أو فواتير وهمية.');
            return 0;
        } else {
            $this->warn(sprintf('⚠️ وجد فحص السلامة المالي %d خللاً/عيوباً في العلاقات والأرصدة.', $discrepancyCount));
            return 1;
        }
    }
}

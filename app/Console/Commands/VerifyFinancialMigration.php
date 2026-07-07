<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\User;
use Modules\Sales\Models\Invoice;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Modules\Companies\Models\BusinessRelation;
use Modules\Accounting\Models\CashBox;
use Illuminate\Support\Facades\DB;

/**
 * أمر التحقق من دقة الترحيل المالي ومطابقة مجاميع الأرصدة الجديدة مع الفواتير التاريخية.
 */
class VerifyFinancialMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financial:verify-migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'التحقق من صحة ومطابقة الأرصدة الدفترية للأطراف بعد الترحيل مع فواتير المبيعات والمشتريات.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== بدء عملية التحقق المالي ومطابقة الأرصدة ===');

        $companies = Company::all();
        $hasErrors = false;

        foreach ($companies as $company) {
            $this->info("--------------------------------------------------");
            $this->info("جاري التحقق للشركة: {$company->name} (#{$company->id})");

            // 1. حساب مجاميع فواتير المبيعات مقابل أرصدة الذمم المدينة الجديدة
            $totalSalesRemaining = 0.00;
            $salesInvoices = Invoice::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereIn('invoice_type_code', ['sale', 'installment_sale', 'service'])
                ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
                ->get();

            foreach ($salesInvoices as $inv) {
                $totalSalesRemaining += ((float)$inv->net_amount - (float)$inv->paid_amount);
            }

            // طرح مرتجعات المبيعات
            $salesReturns = Invoice::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('invoice_type_code', 'sale_return')
                ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
                ->get();

            foreach ($salesReturns as $inv) {
                $totalSalesRemaining -= ((float)$inv->net_amount - (float)$inv->paid_amount);
            }

            $totalReceivableBalance = (float) StakeholderFinancialBalance::where('company_id', $company->id)
                ->where('relation_type', 'receivable')
                ->sum('balance');

            $salesDiscrepancy = abs($totalSalesRemaining - $totalReceivableBalance);
            if ($salesDiscrepancy > 0.01) {
                $this->error(sprintf(
                    "❌ خلل في الذمم المدينة (Receivables): مجموع الفواتير المتبقي (%.2f) لا يطابق الأرصدة المرحلة (%.2f). الفارق: %.2f",
                    $totalSalesRemaining,
                    $totalReceivableBalance,
                    $salesDiscrepancy
                ));
                $hasErrors = true;
            } else {
                $this->info(sprintf(
                    "✅ الذمم المدينة متطابقة: مجموع الفواتير (%.2f) = الأرصدة الدفترية الجديدة (%.2f)",
                    $totalSalesRemaining,
                    $totalReceivableBalance
                ));
            }

            // 2. حساب مجاميع فواتير الشراء مقابل أرصدة الذمم الدائنة الجديدة
            $totalPurchasesRemaining = 0.00;
            $purchaseInvoices = Invoice::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('invoice_type_code', 'purchase')
                ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
                ->get();

            foreach ($purchaseInvoices as $inv) {
                $totalPurchasesRemaining += ((float)$inv->net_amount - (float)$inv->paid_amount);
            }

            // طرح مرتجعات المشتريات
            $purchaseReturns = Invoice::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('invoice_type_code', 'purchase_return')
                ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
                ->get();

            foreach ($purchaseReturns as $inv) {
                $totalPurchasesRemaining -= ((float)$inv->net_amount - (float)$inv->paid_amount);
            }

            $totalPayableBalance = (float) StakeholderFinancialBalance::where('company_id', $company->id)
                ->where('relation_type', 'payable')
                ->sum('balance');

            $purchasesDiscrepancy = abs($totalPurchasesRemaining - $totalPayableBalance);
            if ($purchasesDiscrepancy > 0.01) {
                $this->error(sprintf(
                    "❌ خلل في الذمم الدائنة (Payables): مجموع فواتير الشراء المتبقي (%.2f) لا يطابق الأرصدة المرحلة (%.2f). الفارق: %.2f",
                    $totalPurchasesRemaining,
                    $totalPayableBalance,
                    $purchasesDiscrepancy
                ));
                $hasErrors = true;
            } else {
                $this->info(sprintf(
                    "✅ الذمم الدائنة متطابقة: مجموع فواتير الشراء (%.2f) = الأرصدة الدفترية الجديدة (%.2f)",
                    $totalPurchasesRemaining,
                    $totalPayableBalance
                ));
            }

            // 3. التحقق من أرشفة خزن العملاء/الموردين القديمة وسلامة خزن الموظفين
            $archivedCount = CashBox::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('access_type', 'legacy_archived')
                ->where('is_active', true) // يجب أن تكون معطلة
                ->count();

            if ($archivedCount > 0) {
                $this->error("❌ خلل في حالة الصناديق: هناك {$archivedCount} صندوق مؤرشف ولكن حالته نشطة.");
                $hasErrors = true;
            } else {
                $this->info("✅ فحص أرشفة خزن الأطراف: سليم بالكامل.");
            }

            // 4. التحقق من سلامة خزن الموظفين
            $employeeBoxes = CashBox::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('access_type', 'user_owned')
                ->get();

            $invalidEmployeeBox = false;
            foreach ($employeeBoxes as $box) {
                $isUserStaff = BusinessRelation::where('company_id', $company->id)
                    ->where('user_id', $box->user_id)
                    ->where('relation_type', 'employee')
                    ->exists();

                if (!$isUserStaff) {
                    $this->error("❌ خلل في الخزنة #{$box->id}: مرتبطة بمستخدم ليس لديه علاقة موظف (employee).");
                    $invalidEmployeeBox = true;
                    $hasErrors = true;
                }
            }

            if (!$invalidEmployeeBox) {
                $this->info("✅ فحص خزن الموظفين والعهدة النقدية: سليم ومقترن بالموظفين فقط.");
            }
        }

        $this->info("--------------------------------------------------");
        if ($hasErrors) {
            $this->error('=== فشل عملية التحقق المالي: يوجد تفاوت في البيانات أو الأرصدة! ===');
            return 1;
        } else {
            $this->info('=== نجاح عملية التحقق المالي: جميع الأرصدة والهياكل مطابقة بنسبة 100% ===');
            return 0;
        }
    }
}

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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * أمر ترحيل أرصدة الأطراف وتصنيف العلاقات التجارية وأرشفة الصناديق القديمة للعملاء والموردين.
 */
class MigrateStakeholderBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financial:migrate-balances 
                            {--dry-run : محاكاة عملية حساب الأرصدة وتصنيف العلاقات وعرضها دون حفظ} 
                            {--fix : تنفيذ الترحيل الفعلي وحفظ البيانات وأرشفة الخزن في قاعدة البيانات}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ترحيل أرصدة العملاء والموردين من واقع الفواتير وتصنيف العلاقات التجارية وأرشفة الصناديق القديمة.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== بدء عملية ترحيل النواة المالية وإعادة الهيكلة ===');

        $dryRun = $this->option('dry-run') || !$this->option('fix');
        if ($dryRun) {
            $this->warn('!! وضع المعاينة (Dry-Run) نشط: لن يتم حفظ أي بيانات أو أرشفة أي خزن !!');
        } else {
            $this->warn('!! وضع الترحيل الفعلي نشط: سيتم تحديث الجداول وأرشفة خزن العملاء والموردين !!');
        }

        // إيقاف ميزات الأحداث (Observers) مؤقتاً لتجنب إطلاق أي Side Effects أو تسجيل قيود محاسبية مكررة
        $this->suppressModelEvents();

        $companies = Company::all();
        
        // عدادات دقيقة للتقرير النهائي
        $relationsCreated = 0;
        $relationsUpdated = 0;
        $relationsSkipped = 0;

        $balancesCreated = 0;
        $balancesUpdated = 0;
        $balancesSkipped = 0;

        $cashBoxesArchived = 0;
        $cashBoxesSkipped = 0;

        foreach ($companies as $company) {
            $this->info("--------------------------------------------------");
            $this->info("جاري معالجة الشركة: {$company->name} (#{$company->id})...");

            if (!$dryRun) {
                DB::beginTransaction();
            }

            try {
                // جلب معرفات جميع المستخدمين المرتبطين بالشركة أو الذين لديهم فواتير فيها
                $userIds = DB::table('company_user')
                    ->where('company_id', $company->id)
                    ->pluck('user_id')
                    ->concat(
                        Invoice::withoutGlobalScopes()
                            ->where('company_id', $company->id)
                            ->whereNotNull('user_id')
                            ->pluck('user_id')
                    )
                    ->unique()
                    ->toArray();

                $employeeUserIds = [];

                foreach ($userIds as $userId) {
                    $user = User::withoutGlobalScopes()->find($userId);
                    if (!$user) continue;

                    $companyUser = DB::table('company_user')
                        ->where('company_id', $company->id)
                        ->where('user_id', $userId)
                        ->first();

                    // 1. تحديد وتصنيف العلاقات التجارية للطرف
                    $relations = $this->determineUserRelations($company->id, $user, $companyUser);

                    if (empty($relations)) {
                        // إذا لم تتحدد أي علاقة، نفترض افتراضياً أنه عميل كخيار توافقي
                        $relations[] = 'customer';
                    }

                    // إنشاء العلاقات التجارية في جدول business_relations
                    foreach ($relations as $relType) {
                        $this->line("  - إنشاء علاقة تجارية: مستخدم #{$user->id} ({$user->name}) هو [{$relType}]");
                        
                        $relationModel = null;
                        if (!$dryRun) {
                            $relationModel = BusinessRelation::updateOrCreate(
                                [
                                    'company_id' => $company->id,
                                    'user_id' => $user->id,
                                    'relation_type' => $relType,
                                ],
                                [
                                    'is_active' => true,
                                    'created_by' => 1, // النظام (System Admin)
                                ]
                            );
                        }

                        if ($relationModel) {
                            if ($relationModel->wasRecentlyCreated) {
                                $relationsCreated++;
                            } else {
                                $relationsSkipped++;
                            }
                        } else {
                            // وضع dry-run
                            $relationsCreated++;
                        }
                    }

                    // 2. احتساب الذمم الدائنة والمدينة من الفواتير فقط
                    $hasCustomerRelation = in_array('customer', $relations);
                    $hasSupplierRelation = in_array('supplier', $relations);
                    $isEmployee = in_array('employee', $relations);

                    if ($isEmployee) {
                        $employeeUserIds[] = $user->id;
                    }

                    $receivableBalance = 0.00;
                    $payableBalance = 0.00;

                    // جلب جميع فواتير المستخدم في هذه الشركة
                    $invoices = Invoice::withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->where('user_id', $user->id)
                        ->whereIn('status', ['confirmed', 'paid', 'partially_paid'])
                        ->get();

                    foreach ($invoices as $inv) {
                        $remaining = (float)$inv->net_amount - (float)$inv->paid_amount;
                        $type = $inv->invoice_type_code;

                        if (in_array($type, ['sale', 'installment_sale', 'service'])) {
                            $receivableBalance += $remaining;
                        } elseif ($type === 'purchase') {
                            $payableBalance += $remaining;
                        } elseif ($type === 'sale_return') {
                            $receivableBalance -= $remaining;
                        } elseif ($type === 'purchase_return') {
                            $payableBalance -= $remaining;
                        }
                    }

                    // ترحيل الذمم المدينة (Receivable) للعملاء
                    if ($hasCustomerRelation || abs($receivableBalance) > 0.001) {
                        $this->line(sprintf("    -> ذمم مدينة (receivable): %.2f", $receivableBalance));
                        
                        $oldBal = null;
                        $balModel = null;
                        if (!$dryRun) {
                            $oldBal = StakeholderFinancialBalance::where([
                                'company_id' => $company->id,
                                'user_id' => $user->id,
                                'relation_type' => 'receivable',
                            ])->first();

                            $balModel = StakeholderFinancialBalance::updateOrCreate(
                                [
                                    'company_id' => $company->id,
                                    'user_id' => $user->id,
                                    'relation_type' => 'receivable',
                                ],
                                [
                                    'balance' => $receivableBalance,
                                    'created_by' => 1,
                                ]
                            );
                        }

                        if ($balModel) {
                            if ($balModel->wasRecentlyCreated) {
                                $balancesCreated++;
                            } elseif ($oldBal && abs((float)$oldBal->balance - (float)$receivableBalance) > 0.001) {
                                $balancesUpdated++;
                            } else {
                                $balancesSkipped++;
                            }
                        } else {
                            $balancesCreated++;
                        }
                    }

                    // ترحيل الذمم الدائنة (Payable) للموردين
                    if ($hasSupplierRelation || abs($payableBalance) > 0.001) {
                        $this->line(sprintf("    -> ذمم دائنة (payable): %.2f", $payableBalance));
                        
                        $oldBal = null;
                        $balModel = null;
                        if (!$dryRun) {
                            $oldBal = StakeholderFinancialBalance::where([
                                'company_id' => $company->id,
                                'user_id' => $user->id,
                                'relation_type' => 'payable',
                            ])->first();

                            $balModel = StakeholderFinancialBalance::updateOrCreate(
                                [
                                    'company_id' => $company->id,
                                    'user_id' => $user->id,
                                    'relation_type' => 'payable',
                                ],
                                [
                                    'balance' => $payableBalance,
                                    'created_by' => 1,
                                ]
                            );
                        }

                        if ($balModel) {
                            if ($balModel->wasRecentlyCreated) {
                                $balancesCreated++;
                            } elseif ($oldBal && abs((float)$oldBal->balance - (float)$payableBalance) > 0.001) {
                                $balancesUpdated++;
                            } else {
                                $balancesSkipped++;
                            }
                        } else {
                            $balancesCreated++;
                        }
                    }
                }

                // 3. أرشفة الصناديق القديمة للشركة بالكامل (بما فيها الصناديق اليتيمة)
                $companyCashBoxes = CashBox::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->get();

                foreach ($companyCashBoxes as $box) {
                    if (is_null($box->user_id)) {
                        // الصناديق المشتركة (بدون مستخدم) لا يتم أرشفتها بل نتركها نشطة
                        continue;
                    }

                    $isBoxOwnerEmployee = in_array($box->user_id, $employeeUserIds);
                    if (!$isBoxOwnerEmployee) {
                        // إذا لم يكن مالك الخزنة موظفاً في الشركة، نؤرشف الخزنة
                        if ($box->is_active || $box->access_type !== 'legacy_archived') {
                            $this->line("    [خزنة] أرشفة وتعطيل الخزنة القديمة: ID #{$box->id} ({$box->name}) لمالكها غير الموظف #{$box->user_id}");
                            if (!$dryRun) {
                                $box->is_active = false;
                                $box->access_type = 'legacy_archived';
                                $box->save();
                            }
                            $cashBoxesArchived++;
                        } else {
                            $cashBoxesSkipped++;
                        }
                    } else {
                        // للموظفين، نقوم فقط بتعيين نوع الوصول كـ user_owned
                        if (!$dryRun) {
                            $box->access_type = 'user_owned';
                            $box->save();
                        }
                    }
                }

                if (!$dryRun) {
                    DB::commit();
                }
            } catch (\Exception $e) {
                if (!$dryRun) {
                    DB::rollBack();
                }
                $this->error("❌ فشل معالجة الشركة #{$company->id}: " . $e->getMessage());
                throw $e;
            }
        }

        $this->restoreModelEvents();

        $this->info("--------------------------------------------------");
        $this->info("=== انتهاء عملية الترحيل بنجاح ===");
        $this->line("📊 العلاقات التجارية (business_relations):");
        $this->line("   - المنشأة حديثاً: {$relationsCreated}");
        $this->line("   - المحدثة/المتخطاة: {$relationsSkipped}");
        $this->line("📊 سجلات الأرصدة المالية (stakeholder_financial_balances):");
        $this->line("   - المنشأة حديثاً: {$balancesCreated}");
        $this->line("   - المحدثة: {$balancesUpdated}");
        $this->line("   - المتخطاة (بدون تغيير): {$balancesSkipped}");
        $this->line("📊 الصناديق والخزن القديمة (cash_boxes):");
        $this->line("   - التي تمت أرشفتها حديثاً: {$cashBoxesArchived}");
        $this->line("   - المؤرشفة سابقاً: {$cashBoxesSkipped}");
    }

    /**
     * تحديد وتصنيف العلاقات التجارية للمستخدم بناءً على المعطيات التاريخية وصلاحيات Spatie.
     */
    private function determineUserRelations(int $companyId, User $user, $companyUser): array
    {
        $relations = [];

        // 1. التمييز بين العميل والموظف عن طريق Spatie Permissions
        // صلاحياته سواء منفردة أو في الأدوار
        $directPermissionsCount = DB::table('model_has_permissions')
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->count();

        $roleIds = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->pluck('role_id')
            ->toArray();

        $rolePermissionsCount = 0;
        if (!empty($roleIds)) {
            $rolePermissionsCount = DB::table('role_has_permissions')
                ->whereIn('role_id', $roleIds)
                ->count();
        }

        $totalPermissions = $directPermissionsCount + $rolePermissionsCount;

        if ($totalPermissions > 0) {
            // الموظف: إذا كانت صلاحياته أكبر من صفر
            $relations[] = 'employee';
        } else {
            // العميل أو المورد: إذا كانت صلاحياته صفر أو أقل

            // فحص وجود مبيعات أو فواتير عملاء
            $hasSalesInvoices = Invoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->whereIn('invoice_type_code', ['sale', 'installment_sale', 'service', 'sale_return'])
                ->exists();

            if ($hasSalesInvoices) {
                $relations[] = 'customer';
            }

            // فحص وجود فواتير شراء أو موردين
            $hasPurchaseInvoices = Invoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->whereIn('invoice_type_code', ['purchase', 'purchase_return'])
                ->exists();

            if ($hasPurchaseInvoices) {
                $relations[] = 'supplier';
            }
        }

        return array_unique($relations);
    }

    /**
     * إيقاف Observers لمنع حدوث عمليات مكررة أثناء الترحيل.
     */
    private function suppressModelEvents()
    {
        // إيقاف أحداث الفواتير وسجل المعاملات
        Invoice::flushEventListeners();
        StakeholderFinancialBalance::flushEventListeners();
        BusinessRelation::flushEventListeners();
        CashBox::flushEventListeners();
    }

    /**
     * إعادة تفعيل Observers.
     */
    private function restoreModelEvents()
    {
        // ستقوم Laravel بإعادة تحميل الموديلات تلقائياً في الطلبات القادمة
    }
}

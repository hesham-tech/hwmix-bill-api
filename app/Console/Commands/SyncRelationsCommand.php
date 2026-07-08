<?php

namespace App\Console\Commands;

// أمر كونسول لإعادة مزامنة وإصلاح الخزن النقدية وأرصدة الذمم بناءً على القدرات النشطة الحالية للأطراف.

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Services\CashBoxService;
use Modules\Companies\Models\StakeholderFinancialBalance;
use Modules\Accounting\Models\CashBox;
use App\Enums\CapabilityCode;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncRelationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business:sync-relations {--company_id= : شركة محددة فقط للمزامنة} {--dry-run : عرض التغييرات دون الحفظ الفعلي}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'مزامنة وإصلاح أرصدة الذمم والخزن النقدية للمستخدمين استناداً إلى قدرات علاقاتهم الحالية';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $companyIdOption = $this->option('company_id');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️ يتم تشغيل الأمر في وضع Dry-Run - لن يتم إجراء أي تعديل فعلي بقاعدة البيانات.');
        }

        $companiesQuery = Company::query();
        if ($companyIdOption) {
            $companiesQuery->where('id', $companyIdOption);
        }
        $companies = $companiesQuery->get();

        $this->info("جاري فحص المزامنة لعدد {$companies->count()} شركة...");

        $totalBoxesCreated = 0;
        $totalBalancesCreated = 0;
        $totalBoxesDisabled = 0;

        foreach ($companies as $company) {
            $this->line("--------------------------------------------------");
            $this->info("🏢 فحص الشركة: {$company->name} (ID: {$company->id})");

            // جلب المستخدمين المرتبطين بالشركة
            $users = User::whereHas('businessRelations', function ($q) use ($company) {
                $q->where('company_id', $company->id)
                  ->where('is_active', true);
            })->get();

            $this->comment("تم العثور على عدد {$users->count()} مستخدم نشط.");

            foreach ($users as $user) {
                // 1. مزامنة الخزن النقدية (has_cash_custody)
                $hasCustody = $user->hasCapability(CapabilityCode::HAS_CASH_CUSTODY, $company->id);
                $existingBox = CashBox::where('user_id', $user->id)
                    ->where('company_id', $company->id)
                    ->first();

                if ($hasCustody) {
                    if (!$existingBox) {
                        $this->line("➕ مستخدم [{$user->name}] يملك عهدة نقدية ولكن لا توجد لديه خزنة. سيتم الإنشاء...");
                        if (!$dryRun) {
                            try {
                                $newBox = app(CashBoxService::class)->createDefaultCashBoxForUserCompany(
                                    $user->id,
                                    $company->id,
                                    $user->id
                                );
                                if ($newBox) {
                                    $totalBoxesCreated++;
                                }
                            } catch (Throwable $e) {
                                $this->error("فشل إنشاء خزنة للمستخدم {$user->name}: " . $e->getMessage());
                            }
                        }
                    } elseif (!$existingBox->is_active) {
                        $this->line("🔓 إعادة تفعيل خزنة مستخدم [{$user->name}] الموقوفة حالياً.");
                        if (!$dryRun) {
                            $existingBox->update(['is_active' => true]);
                            $totalBoxesCreated++;
                        }
                    }
                } else {
                    // لا يملك عهدة نقدية ولكنه يملك خزنة نشطة
                    if ($existingBox && $existingBox->is_active) {
                        // تعطيلها إذا كان رصيدها صفراً، أو تحذير إذا كان بها رصيد لمنع تعطيل عهد مالية نشطة
                        if ($existingBox->balance == 0) {
                            $this->line("🔒 تعطيل الخزنة الصفرية للمستخدم [{$user->name}] لزوال قدرة العهدة النقدية.");
                            if (!$dryRun) {
                                $existingBox->update(['is_active' => false]);
                                $totalBoxesDisabled++;
                            }
                        } else {
                            $this->warn("⚠️ مستخدم [{$user->name}] يملك خزنة برصيد ({$existingBox->balance}) ولكن زالت منه قدرة العهدة. ينصح بتسوية العهدة يدوياً.");
                        }
                    }
                }

                // 2. مزامنة أرصدة الذمم (track_receivable / track_payable)
                $trackReceivable = $user->hasCapability(CapabilityCode::TRACK_RECEIVABLE, $company->id);
                $trackPayable = $user->hasCapability(CapabilityCode::TRACK_PAYABLE, $company->id);

                if ($trackReceivable || $trackPayable) {
                    $existingBalance = StakeholderFinancialBalance::where('user_id', $user->id)
                        ->where('company_id', $company->id)
                        ->first();

                    if (!$existingBalance) {
                        $this->line("➕ إنشاء سجل أرصدة ذمم موحد للمستخدم [{$user->name}].");
                        if (!$dryRun) {
                            try {
                                StakeholderFinancialBalance::create([
                                    'company_id' => $company->id,
                                    'user_id' => $user->id,
                                    'receivable_balance' => 0,
                                    'payable_balance' => 0,
                                    'created_by' => $user->id,
                                ]);
                                $totalBalancesCreated++;
                            } catch (Throwable $e) {
                                $this->error("فشل إنشاء سجل أرصدة للمستخدم {$user->name}: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        $this->line("==================================================");
        $this->info("🏁 تم الانتهاء من فحص المزامنة بنجاح.");
        $this->line("خزن نقدية منشأة/مفعلة: {$totalBoxesCreated}");
        $this->line("خزن نقدية معطلة لزوال القدرة: {$totalBoxesDisabled}");
        $this->line("سجلات أرصدة ذمم منشأة: {$totalBalancesCreated}");

        return 0;
    }
}

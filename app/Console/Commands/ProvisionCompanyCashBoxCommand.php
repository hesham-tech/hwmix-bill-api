<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\User;
use Modules\Companies\Models\Branch;
use App\Services\CashBoxService;

use Modules\Accounting\Models\CashBox;

/**
 * أمر تزويد الخزنة الافتراضية للشركة والمستخدم
 */
class ProvisionCompanyCashBoxCommand extends Command
{
    protected $signature = 'hwnix:provision-cashbox {company_id} {user_id} {--cleanup}';
    protected $description = 'تزويد وإنشاء الخزنة النقدية الافتراضية لشركة ومستخدم محدد وتنظيف الخزن المكررة';

    public function handle(CashBoxService $cashBoxService)
    {
        $companyId = (int) $this->argument('company_id');
        $userId = (int) $this->argument('user_id');

        if ($this->option('cleanup')) {
            $lastCashBox = CashBox::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('company_id', $companyId)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastCashBox) {
                $deletedCount = CashBox::withoutGlobalScopes()
                    ->where('user_id', $userId)
                    ->where('company_id', $companyId)
                    ->where('id', '!=', $lastCashBox->id)
                    ->forceDelete();
                $this->info("Cleaned up {$deletedCount} duplicate test cashboxes, keeping CashBox ID: {$lastCashBox->id}.");
            }
            return 0;
        }

        $company = Company::find($companyId);
        $user = User::withoutGlobalScopes()->find($userId);
        $branch = Branch::withoutGlobalScopes()->where('company_id', $companyId)->first();

        if (!$company || !$user) {
            $this->error("Company or User not found.");
            return 1;
        }

        try {
            $cashBox = $cashBoxService->createDefaultCashBoxForUserCompany(
                $userId,
                $companyId,
                $userId,
                $branch?->id
            );

            if ($cashBox) {
                $this->info("Successfully provisioned default cashbox ID: {$cashBox->id} for User {$userId} and Company {$companyId}.");
                return 0;
            }

            $this->error("Failed to provision cashbox.");
            return 1;
        } catch (\Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}

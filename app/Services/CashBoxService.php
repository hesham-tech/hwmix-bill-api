<?php

namespace App\Services;

use App\Services\CashBoxProvisioningService;
use App\Models\CashBox;

/**
 * فئة توافقية للخلف (Legacy Compatibility Wrapper) ترث من خدمة التزويد الجديدة
 * @deprecated يفضل استخدام CashBoxProvisioningService مباشرة
 */
class CashBoxService extends CashBoxProvisioningService
{
    /**
     * الاسم القديم لدالة التزويد
     */
    public function createDefaultCashBoxForUserCompany(int $userId, int $companyId, int $createdById, ?int $branchId = null): ?CashBox
    {
        return $this->provisionDefaultCustody($userId, $companyId, $createdById, $branchId);
    }

    /**
     * الاسم القديم لدالة سحب العهدة
     */
    public function disableDefaultCashBoxForUserCompany(int $userId, int $companyId): bool
    {
        return $this->deprovisionCustody($userId, $companyId);
    }
}

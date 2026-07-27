<?php

// عقد الـ Router — اختيار Provider + Account + Model لكل طلب
namespace Modules\AiPlatform\Contracts\Router;

use Modules\AiPlatform\DTOs\RouterSelectionDTO;
use Modules\AiPlatform\Enums\Capability;

interface AiRouterInterface
{
    /**
     * اختيار Account + Model المناسب
     * يُعيد null عند عدم وجود Account صالح
     */
    public function select(
        Capability $capability,
        int        $companyId,
        array      $requirements, // ['streaming'=>true, 'vision'=>true]
        string     $strategy,     // 'priority'|'cost'|'quality'
    ): ?RouterSelectionDTO;

    /** إبلاغ عن فشل Account لإدارة Failover */
    public function reportFailure(int $accountId, string $errorCode): void;

    /** إبلاغ عن نجاح لتحديث Quota */
    public function reportSuccess(int $accountId, int $tokensUsed): void;
}

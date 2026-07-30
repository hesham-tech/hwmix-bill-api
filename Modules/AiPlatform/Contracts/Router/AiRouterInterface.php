<?php

// عقد الـ Router — اختيار Provider + Account + Model لكل طلب وتسلسل المحاولات المنصية
namespace Modules\AiPlatform\Contracts\Router;

use Illuminate\Support\Collection;
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

    /**
     * جلب جميع الحسابات المؤهلة مرتبة حس الترتيب لمعالجة التتابع السلسلي (Fallback Pipeline)
     */
    public function selectAll(
        Capability $capability,
        int        $companyId,
        array      $requirements,
        string     $strategy
    ): Collection;

    /** إبلاغ عن فشل Account لإدارة Failover */
    public function reportFailure(int $accountId, string $errorCode): void;

    /** إبلاغ عن نجاح لتحديث Quota */
    public function reportSuccess(int $accountId, int $tokensUsed): void;
}

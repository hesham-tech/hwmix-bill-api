<?php

// Action تجريبي من موديول المخزون لطلب قدرة توليد وصف المنتج عبر منصة الذكاء الاصطناعي
namespace Modules\Inventory\Actions;

use Modules\AiPlatform\Facades\AI;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

class GenerateProductDescriptionAction
{
    /**
     * تنفيذ دالة طلب توليد وصف للمنتج عبر AI Platform Facade
     */
    public function execute(int $companyId, string $productName, string $features): ExecutionResultDTO
    {
        return AI::capability('text.generate')
            ->prompt('product.description.generate')
            ->with([
                'product_name' => $productName,
                'features'     => $features,
            ])
            ->forCompany($companyId)
            ->run();
    }
}

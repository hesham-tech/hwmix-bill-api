<?php
// واجهة محرك التحليل الذكي الشامل للنصوص والرسائل والمستندات المنظمة.

namespace Modules\AiPlatform\Contracts\Engines;

use Modules\AiPlatform\DTOs\AnalysisRequestDTO;
use Modules\AiPlatform\DTOs\AnalysisResultDTO;

interface AnalysisEngineInterface
{
    /**
     * تنفيذ طلب تحليل منظم وحفظ النتيجة كأصل مستقل في المنصة وإرجاع DTO المعير.
     */
    public function analyze(AnalysisRequestDTO $request): AnalysisResultDTO;
}

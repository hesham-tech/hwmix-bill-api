<?php
// واجهة تجريد عزل خدمات تحليل الرسائل المالية عن تفاصيل مزودي الذكاء الاصطناعي.

namespace Modules\HwnixCash\Domain\Contracts;

use Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO;

interface FinancialSmsAnalyzerInterface
{
    /**
     * تحليل نص الرسالة المالية وإرجاع DTO موحد ومفحوص.
     */
    public function analyze(string $smsBody, int $companyId): NormalizedFinancialSmsDTO;
}

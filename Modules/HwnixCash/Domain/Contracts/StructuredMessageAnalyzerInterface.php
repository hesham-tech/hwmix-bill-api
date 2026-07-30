<?php
// واجهة تجريد عامة لتحليل مختلف أنواع الرسائل والنصوص المنظمة كخدمة منصة الذكاء الاصطناعي.

namespace Modules\HwnixCash\Domain\Contracts;

use Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO;

interface StructuredMessageAnalyzerInterface
{
    /**
     * تحليل نص الرسالة المنظمة وتوليد DTO معير مفحوص.
     */
    public function analyze(string $smsBody, int $companyId, string $driverKey = 'financial_sms'): NormalizedFinancialSmsDTO;
}

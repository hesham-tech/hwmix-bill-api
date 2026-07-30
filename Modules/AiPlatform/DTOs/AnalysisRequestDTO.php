<?php
// كلاس نقل بيانات طلب التحليل المنظم الموجّه لمحرك التحليل مع التتبع السلسلي الموحد Correlation ID.

namespace Modules\AiPlatform\DTOs;

final class AnalysisRequestDTO
{
    public readonly string $correlationId;

    public function __construct(
        public readonly string $analysisType,          // financial_sms, bank_statement, invoice_ocr, email, whatsapp
        public readonly string $content,               // النص المراد تحليله
        public readonly int $companyId,
        public readonly string $sourceType = 'direct', // hwnix_cash_message, email, whatsapp_message, document, direct
        public readonly ?int $sourceId = null,
        public readonly string $providerKey = 'general', // vodafone_cash, orange_cash, cib, instapay, general
        public readonly array $options = [],
        ?string $correlationId = null
    ) {
        $this->correlationId = $correlationId ?? ('CORR-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

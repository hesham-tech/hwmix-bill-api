<?php
// كلاس تطهير وتطبيع نصوص الرسائل والاسم والمصطلحات البرمجية قبل التحليل.

namespace Modules\HwnixCash\Services\Parsers\Normalizers;

use Modules\HwnixCash\DTOs\IncomingSmsContext;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;

final class TextNormalizer
{
    public function normalize(IncomingSmsContext $context): NormalizedSmsContext
    {
        $cleanBody = $this->normalizeText($context->body);
        $cleanSender = $this->normalizeSender($context->sender);

        return new NormalizedSmsContext(
            normalizedBody: $cleanBody,
            normalizedSender: $cleanSender,
            originalContext: $context
        );
    }

    public function normalizeText(string $text): string
    {
        // 1. تحويل الأرقام العربية والمشرقية إلى إنجليزية
        $text = $this->convertArabicNumeralsToEnglish($text);

        // 2. إزالة رموز RTL و LTR والمحارف غير المرئية (Zero-Width Space/Non-Joiners)
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}]/u', '', $text);

        // 3. توحيد الفواصل والأشكال والتفريغ الزائد
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    public function normalizeSender(string $sender): string
    {
        $clean = $this->normalizeText($sender);
        return trim($clean);
    }

    private function convertArabicNumeralsToEnglish(string $string): string
    {
        $arabicDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];

        $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

        $string = str_replace($arabicDigits, $englishDigits, $string);
        return str_replace($persianDigits, $englishDigits, $string);
    }
}

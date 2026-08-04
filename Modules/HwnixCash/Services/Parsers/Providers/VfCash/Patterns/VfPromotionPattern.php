<?php
// نمط تحديد وتجميع الرسائل الترويجية والدعائية لشركة فودافون كاش لإنهاء المعالجة فورياً بدون تكلفة AI.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfPromotionPattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_PROMO_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::PROMOTION;
    }

    public function getPriority(): int
    {
        return 10; // أولوية منخفضة لرسائل الترويج بعد التأكد من عدم مطابقة أي حركة مالية
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;

        $promoKeywords = [
            'عروض', 'عرض', 'كارت فكة', 'خصم يصل', 'هدية', 'اشحن',
            'استمتع', 'كاش باك', 'ضعف', 'باك', 'مبروك كسبت', 'فرصة'
        ];

        foreach ($promoKeywords as $keyword) {
            if (mb_strpos($body, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        return new PatternMatchResult(
            isMatched: true,
            status: ParserResultStatus::PROMOTION,
            isFinancial: false,
            patternId: $this->getPatternId(),
            messageCategory: MessageCategory::PROMOTION,
            transactionType: TransactionType::NONE,
            isTransaction: false,
            amount: null,
            currency: 'EGP',
            targetPhone: null,
            targetName: null,
            transactionId: null,
            datetime: $context->originalContext->receivedAt,
            balanceFound: false,
            availableBalance: null,
            extractedMetadata: [
                'rule_name' => 'VfPromotionPattern',
                'matched_text' => $context->normalizedBody,
            ]
        );
    }
}

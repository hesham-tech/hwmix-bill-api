<?php
// نمط تفكيك وتحديد رسائل إرسال التحويلات والخصم المالي لشركة فودافون كاش.

namespace Modules\HwnixCash\Services\Parsers\Providers\VfCash\Patterns;

use Modules\HwnixCash\Contracts\Parsers\MessagePatternInterface;
use Modules\HwnixCash\Domain\Enums\MessageCategory;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\Domain\Enums\TransactionType;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;

final class VfSendPattern implements MessagePatternInterface
{
    public function getPatternId(): string
    {
        return 'VF_SEND_001';
    }

    public function getCategory(): MessageCategory
    {
        return MessageCategory::TRANSACTION;
    }

    public function getPriority(): int
    {
        return 90; // أولوية مرتفعة لرسائل الخصم والإرسال
    }

    public function matches(NormalizedSmsContext $context): bool
    {
        $body = $context->normalizedBody;

        $hasSendKeyword = (mb_strpos($body, 'تم تحويل') !== false || mb_strpos($body, 'تم خصم') !== false || mb_strpos($body, 'قمت بتحويل') !== false);
        $hasToKeyword = (mb_strpos($body, 'لـ') !== false || mb_strpos($body, 'إلى') !== false || mb_strpos($body, 'الى') !== false || mb_strpos($body, 'لرقم') !== false);

        return $hasSendKeyword && $hasToKeyword;
    }

    public function extract(NormalizedSmsContext $context): PatternMatchResult
    {
        $body = $context->normalizedBody;

        // 1. استخراج المبلغ المحول والخصم الإجمالي
        $amount = null;
        $totalDeducted = null;

        // أ) البحث عن مبلغ التحويل الصريح (مثال: تم تحويل 10 ج أو 10 ج تحويل)
        if (preg_match('/(?:تم\s+)?تحويل(?:\s+مبلغ)?\s+([\d\.]+)\s*(?:ج\.م|جنيه|ج)?/u', $body, $matches)) {
            $amount = (float) $matches[1];
        } elseif (preg_match('/([\d\.]+)\s*(?:ج\.م|جنيه|ج)?\s*تحويل/u', $body, $matches)) {
            $amount = (float) $matches[1];
        }

        // ب) استخراج الخصم الإجمالي إن وجد (مثال: تم خصم 11 ج)
        if (preg_match('/(?:تم\s+)?خصم\s+([\d\.]+)\s*(?:ج\.م|جنيه|ج)?/u', $body, $matches)) {
            $totalDeducted = (float) $matches[1];
        }

        // ج) إذا لم يعثر على تحويل صريح وعثر على خصم فقط، يكون الخصم هو المبلغ المبدئي
        if ($amount === null && $totalDeducted !== null) {
            $amount = $totalDeducted;
        }

        // 2. استخراج رقم المستلم (مثال: لـ 01012345678 أو لرقم 01097424277)
        $targetPhone = null;
        if (preg_match('/(?:لـ|إلى|الى|لرقم)\s*(?:رقم\s+)?(\+?\d{10,14})/u', $body, $matches)) {
            $targetPhone = $matches[1];
        }

        // 3. استخراج اسم المستلم إن وجد (مثال: المسجل بإسم Ahmad)
        $targetName = null;
        if (preg_match('/(?:المسجل\s+)?(?:بإسم|باسم)\s+([^\.\n\r]+?)(?=\s+على|\s+رقم|\s+كود|\s+مصاريف|\s+رسوم|\s+رصيد|\s*$)/u', $body, $matches)) {
            $targetName = trim($matches[1]);
        }

        // 4. استخراج كود/رقم العملية (مثال: كود العملية: 105234918 أو رقم العملية 022332946088 :)
        $transactionId = null;
        if (preg_match('/(?:كود|رقم)\s*العملية\s*:?\s*(\d+)/u', $body, $matches)) {
            $transactionId = $matches[1];
        }

        // 5. استخراج الرصيد المتبقي إن وجد (مثال: رصيد حسابك فى فودافون كاش الحالي 2459.83)
        $availableBalance = null;
        $balanceFound = false;
        if (preg_match('/رصيد(?:ك| حسابك)?(?:\s+فى|\s+في)?(?:\s+فودافون\s+كاش)?(?:\s+الحالي|\s+المتاح)?\s*:?\s*([\d\.]+)/u', $body, $matches)) {
            $availableBalance = (float) $matches[1];
            $balanceFound = true;
        }

        // 6. استخراج مصاريف الخدمة/العمولة بشكل متطور وثلاثي الأبعاد
        $serviceFee = null;

        // أ) النمط المباشر للعمولة/المصاريف (مثال: مصاريف الخدمة 1.00 ج.م أو بمصاريف 1 جنيه أو الرسوم 1 ج)
        if (preg_match('/(?:مصاريف|رسوم|عمولة|الرسوم|المصاريف|بمصاريف)\s*(?:الخدمة|المحفظة|التحويل|الإرسال)?\s*:?\s*([\d\.]+)/u', $body, $matches)) {
            $serviceFee = (float) $matches[1];
        } 
        // ب) نمط الخصم التفصيلي بالأقواس (مثال: خصم 11 ج (10 ج تحويل + 1 ج مصاريف))
        elseif (preg_match('/\+\s*([\d\.]+)\s*(?:ج|ج\.م|جنيه)?\s*(?:مصاريف|رسوم|عمولة)/u', $body, $matches)) {
            $serviceFee = (float) $matches[1];
        }

        // ج) الحساب الفارق التلقائي في حال وجود إجمالي مخصوم ومبلغ تحويل
        if ($serviceFee === null && $totalDeducted !== null && $amount !== null && $totalDeducted > $amount) {
            $serviceFee = round($totalDeducted - $amount, 2);
        }

        $serviceFee = max(0, (float) ($serviceFee ?? 0.0));

        // د) تصحيح مبلغ التحويل إذا كان الخصم الإجمالي يساوي مبلغ التحويل + العمولة
        if ($totalDeducted !== null && $serviceFee > 0 && $amount >= $totalDeducted) {
            $amount = round($totalDeducted - $serviceFee, 2);
        }

        return new PatternMatchResult(
            isMatched: true,
            status: ParserResultStatus::SUCCESS,
            isFinancial: true,
            patternId: $this->getPatternId(),
            messageCategory: MessageCategory::TRANSACTION,
            transactionType: TransactionType::SEND,
            isTransaction: true,
            amount: $amount,
            fee: $serviceFee,
            currency: 'EGP',
            targetPhone: $targetPhone,
            targetName: $targetName,
            transactionId: $transactionId,
            datetime: $context->originalContext->receivedAt,
            balanceFound: $balanceFound,
            availableBalance: $availableBalance,
            extractedMetadata: [
                'rule_name' => 'VfSendPattern',
                'service_fee' => $serviceFee,
                'matched_text' => $body,
            ]
        );
    }
}

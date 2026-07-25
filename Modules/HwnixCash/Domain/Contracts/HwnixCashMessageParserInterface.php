<?php
// واجهة نقطة التوسع المعمارية لنقل وتمرير الرسائل إلى محرك تحليل الرسائل المستقبلي.

namespace Modules\HwnixCash\Domain\Contracts;

use Modules\HwnixCash\Domain\Entities\SmsMessage;

interface HwnixCashMessageParserInterface
{
    /**
     * نقطة التوسع لتحليل الرسالة النصية المعتمدة واستخراج المعاملات المالية مستقبلاً.
     */
    public function parse(SmsMessage $message): void;
}

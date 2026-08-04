<?php
// كلاس نقل بيانات سياق الرسالة بعد تطهير النص وتوحيد الأرقام والحروف.

namespace Modules\HwnixCash\DTOs;

final class NormalizedSmsContext
{
    public function __construct(
        public readonly string $normalizedBody,
        public readonly string $normalizedSender,
        public readonly IncomingSmsContext $originalContext
    ) {}
}

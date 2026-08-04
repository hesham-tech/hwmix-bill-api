<?php
// كلاس نقل البيانات الوصفية لعملية التحليل وتتبع نتائج المزود والنمط.

namespace Modules\HwnixCash\DTOs;

final class ParserMetadata
{
    public function __construct(
        public readonly string $patternId,
        public readonly string $parserStage,
        public readonly string $providerKey,
        public readonly string $senderAlias,
        public readonly array $extra = [],
        public readonly ?string $parsedBy = null
    ) {}
}

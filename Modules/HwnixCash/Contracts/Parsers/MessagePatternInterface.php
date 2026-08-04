<?php
// واجهة أنماط تفكيك الرسائل الفردية.

namespace Modules\HwnixCash\Contracts\Parsers;

use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\PatternMatchResult;
use Modules\HwnixCash\Domain\Enums\MessageCategory;

interface MessagePatternInterface
{
    public function getPatternId(): string;
    public function getCategory(): MessageCategory;
    public function getPriority(): int;
    public function matches(NormalizedSmsContext $context): bool;
    public function extract(NormalizedSmsContext $context): PatternMatchResult;
}

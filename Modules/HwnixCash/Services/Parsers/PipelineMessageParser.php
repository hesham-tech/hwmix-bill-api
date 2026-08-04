<?php
// المنسق الرئيسي لسلسلة محرك تحليل الرسائل بالنظام.

namespace Modules\HwnixCash\Services\Parsers;

use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Contracts\Parsers\MessageParserInterface;
use Modules\HwnixCash\Contracts\Parsers\ParserStageInterface;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\DTOs\IncomingSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;
use Modules\HwnixCash\Services\Parsers\Normalizers\TextNormalizer;

final class PipelineMessageParser implements MessageParserInterface
{
    /** @var array<ParserStageInterface> */
    private array $stages;

    public function __construct(
        private readonly ParserRegistry $registry,
        private readonly TextNormalizer $normalizer,
        array $stages = []
    ) {
        $this->stages = $stages;
    }

    public function parse(IncomingSmsContext $context): ParsedSmsResultDTO
    {
        // 1. تطهير وتطبيع نص الرسالة واسم المرسل أولاً
        $normalizedContext = $this->normalizer->normalize($context);

        Log::info("⚙️ [MessageParserEngine] Starting Pipeline for Sender '{$context->sender}' (Normalized: '{$normalizedContext->normalizedSender}')");

        // 2. تشغيل مراحل الـ Pipeline بالترتيب
        foreach ($this->stages as $index => $stage) {
            $stageNum = $index + 1;
            Log::info("⚙️ [MessageParserEngine] Running Stage {$stageNum}: " . get_class($stage));

            $result = $stage->process($normalizedContext, $this->registry);

            // إذا نجح التحليل أو كانت رسالة إشعار/دعاية، تنتهي السلسلة ويُرجع الناتج فورياً
            if ($result->isSupported || $result->status === ParserResultStatus::PROMOTION || $result->status === ParserResultStatus::NON_FINANCIAL) {
                Log::info("✅ [MessageParserEngine] Stage {$stageNum} SUCCESS! Status: {$result->status->value}, Pattern: {$result->metadata->patternId}");
                return $result;
            }
        }

        Log::warning("⚠️ [MessageParserEngine] All Pipeline Stages failed for message from '{$context->sender}'");

        return ParsedSmsResultDTO::failed(ParserResultStatus::UNKNOWN_PATTERN);
    }
}

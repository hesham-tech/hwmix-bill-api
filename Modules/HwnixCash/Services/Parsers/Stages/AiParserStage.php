<?php
// المرحلة الثانية في السلسلة: محول الذكاء الاصطناعي كملجأ أخير عند عدم مطابقة القواعد المبرمجة.

namespace Modules\HwnixCash\Services\Parsers\Stages;

use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Engines\AnalysisEngineInterface;
use Modules\AiPlatform\DTOs\AnalysisRequestDTO;
use Modules\HwnixCash\Contracts\Parsers\ParserStageInterface;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\DTOs\NormalizedSmsContext;
use Modules\HwnixCash\DTOs\ParsedSmsResultDTO;
use Modules\HwnixCash\DTOs\ParserMetadata;
use Modules\HwnixCash\Services\Parsers\ParserRegistry;
use Throwable;

final class AiParserStage implements ParserStageInterface
{
    public function __construct(
        private readonly ?AnalysisEngineInterface $analysisEngine = null
    ) {}

    public function process(NormalizedSmsContext $context, ParserRegistry $registry): ParsedSmsResultDTO
    {
        Log::info("🧠 [Parser Pipeline Stage 2] Fallback to AI Parser for message from '{$context->normalizedSender}'");

        if (!$this->analysisEngine) {
            Log::warning("⚠️ [Parser Pipeline Stage 2] AnalysisEngine is not bound. Returning unknown_pattern.");
            return ParsedSmsResultDTO::failed(ParserResultStatus::UNKNOWN_PATTERN);
        }

        try {
            $orig = $context->originalContext;
            $analysisResult = $this->analysisEngine->analyze(new AnalysisRequestDTO(
                analysisType: 'financial_sms',
                content: $orig->body,
                companyId: 1,
                sourceType: 'hwnix_cash_message',
                providerKey: $orig->providerKeyHint ?? 'general'
            ));

            $status = ($analysisResult->messageType === 'unknown' || !empty($analysisResult->validationErrors))
                ? ParserResultStatus::UNKNOWN_PATTERN
                : ParserResultStatus::SUCCESS;

            return new ParsedSmsResultDTO(
                status: $status,
                isSupported: $status === ParserResultStatus::SUCCESS,
                isFinancial: $analysisResult->isTransaction,
                parserName: 'GeminiAiParserAdapter',
                parserVersion: '1.0.0',
                messageType: $analysisResult->messageType,
                transactionType: $analysisResult->isTransaction ? 'financial' : 'none',
                isTransaction: $analysisResult->isTransaction,
                amount: $analysisResult->amount,
                currency: $analysisResult->currency ?? 'EGP',
                targetPhone: $analysisResult->targetPhone,
                targetName: $analysisResult->targetName,
                transactionId: $analysisResult->transactionId,
                datetime: $analysisResult->datetime,
                balanceFound: $analysisResult->balanceFound,
                availableBalance: $analysisResult->availableBalance,
                metadata: new ParserMetadata(
                    patternId: 'AI_GEMINI_FALLBACK',
                    parserStage: 'ai_fallback',
                    providerKey: 'ai_llm',
                    senderAlias: $context->normalizedSender,
                    extra: [
                        'confidenceScore' => $analysisResult->confidenceScore,
                        'validationErrors' => $analysisResult->validationErrors,
                    ]
                )
            );

        } catch (Throwable $e) {
            Log::error("❌ [Parser Pipeline Stage 2] AI Fallback Exception: {$e->getMessage()}");
            return ParsedSmsResultDTO::failed(ParserResultStatus::ERROR);
        }
    }
}

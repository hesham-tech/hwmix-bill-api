<?php
// كلاس التنسيق الرئيسي لإدارة معالجة الرسائل المالية وتوجيه المعاملات استناداً إلى منصة الذكاء الاصطناعي HWNix AI Platform.

namespace Modules\HwnixCash\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Contracts\Parsers\MessageParserInterface;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\Domain\Enums\ParserResultStatus;
use Modules\HwnixCash\DTOs\IncomingSmsContext;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Services\Processing\DuplicateChecker;
use Modules\HwnixCash\Services\Processing\SmsMessageFinalizer;
use Modules\HwnixCash\Services\Processing\WalletBalanceUpdater;
use Modules\HwnixCash\Services\Processing\WalletTransactionCreator;
use Throwable;

class HwnixCashMessageParserService implements HwnixCashMessageParserInterface
{
    public const PARSER_VERSION = '5.0.0-lite';

    public function __construct(
        protected MessageParserInterface $pipelineParser,
        protected DuplicateChecker $duplicateChecker,
        protected WalletBalanceUpdater $balanceUpdater,
        protected WalletTransactionCreator $transactionCreator,
        protected SmsMessageFinalizer $messageFinalizer
    ) {}

    public function parse(SmsMessage $message): void
    {
        Log::info("🧠 [HWNixCash SMS Pipeline] Starting v5 Lite Pipeline Analysis for Message ID {$message->id} from Sender '{$message->phoneNumber}'");

        try {
            // 1. بناء سياق الرسالة القادمة النقي
            $context = new IncomingSmsContext(
                body: $message->messageBody,
                sender: $message->phoneNumber,
                rawMessage: $message,
                receivedAt: $message->sentAt ? (string) $message->sentAt : now()->toIso8601String(),
                providerKeyHint: null,
                deviceId: $message->smsDeviceId
            );

            // 2. تحليل الرسالة عبر السلسلة المعمارية (RuleBasedStage -> AiStage Fallback)
            $parsedResult = $this->pipelineParser->parse($context);

            Log::info("📊 [HWNixCash SMS Pipeline] Pipeline Analysis Output for Message ID {$message->id}:", [
                'message_id' => $message->id,
                'status' => $parsedResult->status->value,
                'is_financial' => $parsedResult->isFinancial,
                'message_type' => $parsedResult->messageType,
                'transaction_type' => $parsedResult->transactionType,
                'amount' => $parsedResult->amount,
                'currency' => $parsedResult->currency,
                'target_phone' => $parsedResult->targetPhone,
                'transaction_id' => $parsedResult->transactionId,
                'available_balance' => $parsedResult->availableBalance,
                'pattern_id' => $parsedResult->metadata->patternId,
                'stage' => $parsedResult->metadata->parserStage,
            ]);

            // 3. المعالجة السريعة للرسائل غير المالية والدعائية (إعفاء من الأثر المالي والذكي)
            if ($parsedResult->status === ParserResultStatus::PROMOTION || $parsedResult->status === ParserResultStatus::NON_FINANCIAL) {
                Log::info("ℹ️ [HWNixCash SMS Pipeline] Message ID {$message->id} is {$parsedResult->status->value}. Processing completed immediately.");
                $this->messageFinalizer->markAsProcessed($message->id);
                return;
            }

            // 4. تنفيذ العمليات المحاسبية والقيود داخل DB Transaction ذرية ومحمية
            DB::transaction(function () use ($message, $parsedResult) {

                // البحث عن الخط المالي الفيزيائي ومصدر الرسائل المعتمد والحساب المالي المرتبط بهما
                $line = $this->resolveLine($message);

                if (!$line) {
                    Log::warning("⚠️ [HWNixCash SMS Pipeline] FAILED - No matching SIM line found for Message ID {$message->id} in Company ID {$message->companyId}. Marked as 'needs_review'");
                    $this->messageFinalizer->markAsNeedsReview($message->id, 'لم يتم العثور على خط مالي مرتبط بالرسالة');
                    return;
                }

                $matchingSource = \Modules\HwnixCash\Models\HwnixCashMessageSource::where('company_id', $message->companyId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($message) {
                        $q->where('sender_identifier', $message->phoneNumber)
                          ->orWhereRaw('LOWER(sender_identifier) = LOWER(?)', [$message->phoneNumber]);
                    })
                    ->first();

                if (!$matchingSource) {
                    $matchingSource = \Modules\HwnixCash\Models\HwnixCashMessageSource::where('company_id', $message->companyId)
                        ->where('is_active', true)
                        ->first();
                }

                if (!$matchingSource) {
                    Log::warning("⚠️ [HWNixCash SMS Pipeline] FAILED - Sender identifier '{$message->phoneNumber}' is not active MessageSource");
                    $this->messageFinalizer->markAsNeedsReview($message->id, 'مصدر الرسالة غير مسجل كمصدر معتمد بالشركة');
                    return;
                }

                // المطابقة باستخدام (Line + MessageSource أو Line + Provider أو Line المباشر)
                $financialAccount = \Modules\HwnixCash\Models\HwnixCashFinancialAccount::where('company_id', $message->companyId)
                    ->where('line_id', $line->id)
                    ->where(function ($q) use ($matchingSource) {
                        $q->where('message_source_id', $matchingSource->id)
                          ->orWhereHas('messageSource', function ($q2) use ($matchingSource) {
                              $q2->where('provider', $matchingSource->provider);
                          });
                    })
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$financialAccount) {
                    $financialAccount = \Modules\HwnixCash\Models\HwnixCashFinancialAccount::where('company_id', $message->companyId)
                        ->where('line_id', $line->id)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$financialAccount) {
                    Log::warning("⚠️ [HWNixCash SMS Pipeline] FAILED - No active FinancialAccount linked to Line ID {$line->id} and MessageSource ID {$matchingSource->id} ('{$message->phoneNumber}')");
                    $this->messageFinalizer->markAsNeedsReview($message->id, "لم يتم العثور على حساب مالي مرتبط بالخط ورسائل '{$message->phoneNumber}'");
                    return;
                }

                Log::info("📱 [HWNixCash SMS Pipeline] Resolved FinancialAccount ID {$financialAccount->id} ('{$financialAccount->name}') on Line ID {$line->id} for Message ID {$message->id}");

                // 5. تحديث الرصيد الفعلي (actual_balance) على مستوى الحساب المالي فورياً
                if ($parsedResult->balanceFound && $parsedResult->availableBalance !== null) {
                    Log::info("💵 [HWNixCash SMS Pipeline] Updating Actual Balance to {$parsedResult->availableBalance} EGP for FinancialAccount ID {$financialAccount->id}");
                    $this->balanceUpdater->updateActualBalance($financialAccount, $parsedResult->availableBalance);
                }

                // 6. طبقة القرار التجارية بالنظام
                if (!$parsedResult->isSupported || $parsedResult->status === ParserResultStatus::UNKNOWN_PATTERN || $parsedResult->status === ParserResultStatus::ERROR) {
                    Log::warning("⚠️ [HWNixCash SMS Pipeline] Message ID {$message->id} classified as unknown or unsupported. Marked as 'needs_review'. Pattern: {$parsedResult->metadata->patternId}");
                    $this->messageFinalizer->markAsNeedsReview($message->id, 'نوع الحركة غير معروف أو فشل التفكيك البرمجي وتم توجيهه للمراجعة البشرية');
                    return;
                }

                if ($parsedResult->isFinancial && $parsedResult->isTransaction && $parsedResult->amount !== null && $parsedResult->amount > 0) {
                    // فحص التكرار عبر المستودع
                    if ($this->duplicateChecker->isDuplicateTransaction($message->companyId, $financialAccount->id, $parsedResult->transactionId, $message->id)) {
                        Log::info("ℹ️ [HWNixCash SMS Pipeline] Duplicate transaction ID '{$parsedResult->transactionId}' skipped for Message ID {$message->id}");
                        $this->messageFinalizer->markAsProcessed($message->id);
                        return;
                    }

                    // تحويل ParsedSmsResultDTO إلى NormalizedFinancialSmsDTO لـ TransactionCreator
                    $normalizedDto = new \Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO(
                        messageType: $parsedResult->messageType,
                        isTransaction: $parsedResult->isTransaction,
                        amount: $parsedResult->amount,
                        currency: $parsedResult->currency,
                        targetPhone: $parsedResult->targetPhone,
                        targetName: $parsedResult->targetName,
                        transactionId: $parsedResult->transactionId,
                        datetime: $parsedResult->datetime,
                        balanceFound: $parsedResult->balanceFound,
                        availableBalance: $parsedResult->availableBalance,
                        confidenceScore: 1.0,
                        schemaVersion: '5.0-lite',
                        promptVersion: '5.0-lite',
                        parserVersion: $parsedResult->parserVersion,
                        validationErrors: [],
                        executionMetadata: [
                            'pattern_id' => $parsedResult->metadata->patternId,
                            'parser_stage' => $parsedResult->metadata->parserStage,
                            'provider_key' => $parsedResult->metadata->providerKey,
                            'parsed_by' => $parsedResult->metadata->parsedBy ?? $parsedResult->parserName,
                        ],
                        rawAiOutput: json_encode($parsedResult)
                    );

                    // إنشاء المعاملة المالية وتعديل الرصيد الحسابي بدفتر الأستاذ على كيان الحساب المالي
                    $walletTx = $this->transactionCreator->createTransaction($financialAccount, $message, $normalizedDto);
                    if ($walletTx) {
                        Log::info("💰 [HWNixCash SMS Pipeline] SUCCESS! Created Wallet Transaction ID {$walletTx->id} for FinancialAccount ID {$financialAccount->id}. Amount: {$walletTx->amount} EGP");
                    }
                } else {
                    Log::info("ℹ️ [HWNixCash SMS Pipeline] Message ID {$message->id} is non-transactional (Amount: {$parsedResult->amount}, IsTx: {$parsedResult->isTransaction}). Processing complete without financial mutation.");
                }

                // 7. إنهاء المعالجة وتثبيت الحالة بنجاح
                $this->messageFinalizer->markAsProcessed($message->id);
            });

        } catch (Throwable $e) {
            Log::error("❌ [HWNixCash SMS Pipeline] EXCEPTION during parsing Message ID {$message->id}: {$e->getMessage()}", [
                'message_id' => $message->id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->messageFinalizer->markAsNeedsReview($message->id, 'حدث خطأ استثنائي أثناء معالجة الرسالة: ' . $e->getMessage());
        }
    }

    protected function resolveLine(SmsMessage $message): ?HwnixCashLine
    {
        $line = null;

        if ($message->smsLineId) {
            $line = HwnixCashLine::where('id', $message->smsLineId)
                ->lockForUpdate()
                ->first();
        }

        if (!$line && $message->smsDeviceId) {
            $line = HwnixCashLine::where('company_id', $message->companyId)
                ->whereHas('device', function ($q) use ($message) {
                    $q->where('id', $message->smsDeviceId);
                })
                ->lockForUpdate()
                ->first();
        }

        if (!$line) {
            $line = HwnixCashLine::where('company_id', $message->companyId)
                ->lockForUpdate()
                ->first();
        }

        return $line;
    }
}

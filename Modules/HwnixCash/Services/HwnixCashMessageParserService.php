<?php
// كلاس التنسيق الرئيسي لإدارة معالجة الرسائل المالية وتوجيه المعاملات استناداً إلى منصة الذكاء الاصطناعي HWNix AI Platform.

namespace Modules\HwnixCash\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Engines\AnalysisEngineInterface;
use Modules\AiPlatform\DTOs\AnalysisRequestDTO;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageParserInterface;
use Modules\HwnixCash\Domain\Entities\SmsMessage;
use Modules\HwnixCash\Models\HwnixCashLine;
use Modules\HwnixCash\Services\Processing\DuplicateChecker;
use Modules\HwnixCash\Services\Processing\SmsMessageFinalizer;
use Modules\HwnixCash\Services\Processing\WalletBalanceUpdater;
use Modules\HwnixCash\Services\Processing\WalletTransactionCreator;
use Throwable;

class HwnixCashMessageParserService implements HwnixCashMessageParserInterface
{
    public const PARSER_VERSION = '1.0.0';

    public function __construct(
        protected AnalysisEngineInterface $analysisEngine,
        protected DuplicateChecker $duplicateChecker,
        protected WalletBalanceUpdater $balanceUpdater,
        protected WalletTransactionCreator $transactionCreator,
        protected SmsMessageFinalizer $messageFinalizer
    ) {}

    public function parse(SmsMessage $message): void
    {
        Log::info("🧠 [HWNixCash SMS Pipeline] Step 4/5: Starting AI Structural Analysis for Message ID {$message->id} from Sender '{$message->phoneNumber}'");

        try {
            // 1. طلب التحليل الهيكلي المنظم وتثبيت النتيجة في المنصة (Single Source of Truth)
            $analysisResult = $this->analysisEngine->analyze(new AnalysisRequestDTO(
                analysisType: 'financial_sms',
                content: $message->messageBody,
                companyId: $message->companyId ?? 1,
                sourceType: 'hwnix_cash_message',
                sourceId: $message->id,
                providerKey: 'general'
            ));

            Log::info("📊 [HWNixCash SMS Pipeline] AI Structural Analysis Output for Message ID {$message->id}:", [
                'message_id' => $message->id,
                'is_transaction' => $analysisResult->isTransaction,
                'message_type' => $analysisResult->messageType,
                'amount' => $analysisResult->amount,
                'currency' => $analysisResult->currency,
                'target_phone' => $analysisResult->targetPhone,
                'target_name' => $analysisResult->targetName,
                'transaction_id' => $analysisResult->transactionId,
                'available_balance' => $analysisResult->availableBalance,
                'confidence_score' => $analysisResult->confidenceScore,
                'validation_errors' => $analysisResult->validationErrors,
            ]);

            // 2. تنفيذ العمليات المحاسبية والقيود داخل DB Transaction ذرية ومحمية
            DB::transaction(function () use ($message, $analysisResult) {

                // البحث عن الخط المالي المناسب مع قفل التزامن للحماية من Race Conditions
                $line = $this->resolveLine($message);

                if (!$line) {
                    Log::warning("⚠️ [HWNixCash SMS Pipeline] Step 4.1/5: FAILED - No matching SIM line found for Message ID {$message->id} in Company ID {$message->companyId}. Marked as 'needs_review'");
                    $this->messageFinalizer->markAsNeedsReview($message->id, 'لم يتم العثور على خط مالي مرابط بالرسالة');
                    return;
                }

                Log::info("📱 [HWNixCash SMS Pipeline] Step 4.1/5: Resolved SIM Line ID {$line->id} ('{$line->phone_number}') for Message ID {$message->id}");

                // 3. تحديث الرصيد الفعلي (actual_balance) إذا ثبت وجوده بالرسالة كـ Source of Truth
                if ($analysisResult->balanceFound && $analysisResult->availableBalance !== null) {
                    Log::info("💵 [HWNixCash SMS Pipeline] Updating Actual Line Balance to {$analysisResult->availableBalance} EGP for Line ID {$line->id}");
                    $this->balanceUpdater->updateActualBalance($line, $analysisResult->availableBalance);
                }

                // 4. طبقة القرار التجارية بالنظام (System Decision Layer)
                if ($analysisResult->messageType === 'unknown' || !empty($analysisResult->validationErrors)) {
                    // توجيه الرسالة لقائمة المراجعة البشرية (Unknown Queue)
                    $errorText = implode('; ', $analysisResult->validationErrors) ?: 'نوع الحركة غير معروف وتم توجيهه للمراجعة البشرية';
                    Log::warning("⚠️ [HWNixCash SMS Pipeline] Step 4.2/5: Message ID {$message->id} classified as unknown or contains validation errors. Marked as 'needs_review'. Error: {$errorText}");
                    $this->messageFinalizer->markAsNeedsReview($message->id, $errorText);
                    return;
                }

                if ($analysisResult->isTransaction && $analysisResult->amount !== null && $analysisResult->amount > 0) {
                    // فحص التكرار عبر المستودع
                    if ($this->duplicateChecker->isDuplicateTransaction($message->companyId, $line->id, $analysisResult->transactionId, $message->id)) {
                        Log::info("ℹ️ [HWNixCash SMS Pipeline] Step 4.3/5: Duplicate transaction ID '{$analysisResult->transactionId}' skipped for Message ID {$message->id}");
                        $this->messageFinalizer->markAsProcessed($message->id);
                        return;
                    }

                    // تحويل AnalysisResultDTO إلى NormalizedFinancialSmsDTO مؤقت متوافق لـ TransactionCreator
                    $normalizedDto = new \Modules\HwnixCash\DTOs\NormalizedFinancialSmsDTO(
                        messageType: $analysisResult->messageType,
                        isTransaction: $analysisResult->isTransaction,
                        amount: $analysisResult->amount,
                        currency: $analysisResult->currency,
                        targetPhone: $analysisResult->targetPhone,
                        targetName: $analysisResult->targetName,
                        transactionId: $analysisResult->transactionId,
                        datetime: $analysisResult->datetime,
                        balanceFound: $analysisResult->balanceFound,
                        availableBalance: $analysisResult->availableBalance,
                        confidenceScore: $analysisResult->confidenceScore,
                        schemaVersion: $analysisResult->schemaVersion,
                        promptVersion: $analysisResult->promptVersion,
                        parserVersion: self::PARSER_VERSION,
                        validationErrors: $analysisResult->validationErrors,
                        executionMetadata: $analysisResult->executionMetadata,
                        rawAiOutput: $analysisResult->normalizedJson
                    );

                    // إنشاء المعاملة المالية وتعديل الرصيد الحسابي بدفتر الأستاذ
                    $walletTx = $this->transactionCreator->createTransaction($line, $message, $normalizedDto);
                    if ($walletTx) {
                        Log::info("💰 [HWNixCash SMS Pipeline] Step 5/5: SUCCESS! Created Wallet Transaction ID {$walletTx->id} for Message ID {$message->id}. Amount: {$walletTx->amount} EGP");
                    }
                } else {
                    Log::info("ℹ️ [HWNixCash SMS Pipeline] Step 5/5: Message ID {$message->id} is non-transactional (Amount: {$analysisResult->amount}, IsTx: {$analysisResult->isTransaction}). Processing complete without financial mutation.");
                }

                // 5. إنهاء المعالجة وتثبيت الحالة بنجاح
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

<?php
// كلاس التنسيق الرئيسي لإدارة معالجة الرسائل المالية وتوجيه العمليات بين كلاسات النظام والتحقق المتقدم.

namespace Modules\HwnixCash\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\HwnixCash\Domain\Contracts\FinancialSmsAnalyzerInterface;
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
    public function __construct(
        protected FinancialSmsAnalyzerInterface $analyzer,
        protected DuplicateChecker $duplicateChecker,
        protected WalletBalanceUpdater $balanceUpdater,
        protected WalletTransactionCreator $transactionCreator,
        protected SmsMessageFinalizer $messageFinalizer
    ) {}

    public function parse(SmsMessage $message): void
    {
        Log::info("[HwnixCashMessageParserService Orchestrator] Received incoming SMS ID {$message->id} from {$message->phoneNumber}");

        try {
            // 1. تحليل وتطهير الرسالة بواسطة طبقة التجريد وتوليد DTO المعير المفحوص
            $dto = $this->analyzer->analyze($message->messageBody, $message->companyId ?? 1);

            // 2. تنفيذ العمليات المحاسبية والقيود داخل DB Transaction ذرية ومحمية
            DB::transaction(function () use ($message, $dto) {

                // البحث عن الخط المالي المناسب مع قفل التزامن للحماية من Race Conditions
                $line = $this->resolveLine($message);

                if (!$line) {
                    Log::warning("[HwnixCashMessageParserService] No matching line found for Message ID {$message->id} in Company {$message->companyId}");
                    $this->messageFinalizer->markAsNeedsReview($message->id, 'لم يتم العثور على خط مالي مرابط بالرسالة');
                    return;
                }

                // 3. تحديث الرصيد الفعلي (actual_balance) إذا ثبت وجوده بالرسالة كـ Source of Truth
                if ($dto->balanceFound && $dto->availableBalance !== null) {
                    $this->balanceUpdater->updateActualBalance($line, $dto->availableBalance);
                }

                // 4. طبقة القرار التجارية بالنظام (System Decision Layer based on messageType & confidence)
                if ($dto->messageType === 'unknown' || !empty($dto->validationErrors)) {
                    // رسالة غير معروفة أو تحتوي على أخطاء تدقيق -> تحول لقائمة المراجعة البشرية بدلاً من تجاهلها
                    $errorText = implode('; ', $dto->validationErrors) ?: 'نوع الحركة غير معروف وتم توجيهه للمراجعة البشرية';
                    $this->messageFinalizer->markAsNeedsReview($message->id, $errorText);
                    return;
                }

                if ($dto->isTransaction && $dto->amount !== null && $dto->amount > 0) {
                    // فحص التكرار عبر مستودع البيانات (Idempotency Check)
                    if ($this->duplicateChecker->isDuplicateTransaction($message->companyId, $line->id, $dto->transactionId, $message->id)) {
                        Log::info("[HwnixCashMessageParserService] Duplicate transaction skipped for Message ID {$message->id}");
                        $this->messageFinalizer->markAsProcessed($message->id);
                        return;
                    }

                    // إنشاء المعاملة المالية وتعديل الرصيد الحسابي بدفتر الأستاذ
                    $this->transactionCreator->createTransaction($line, $message, $dto);
                }

                // 5. إنهاء المعالجة وتثبيت الحالة بنجاح
                $this->messageFinalizer->markAsProcessed($message->id);
            });

        } catch (Throwable $e) {
            Log::error("[HwnixCashMessageParserService] Exception during parsing Message ID {$message->id}: " . $e->getMessage(), [
                'exception' => $e
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

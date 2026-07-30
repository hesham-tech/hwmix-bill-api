<?php

namespace Modules\AiPlatform\Engines;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Engines\CostEngineInterface;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\Contracts\Engines\PolicyEngineInterface;
use Modules\AiPlatform\Contracts\Router\AiRouterInterface;
use Modules\AiPlatform\Contracts\Security\SecretVaultInterface;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;
use Modules\AiPlatform\Models\AiExecutionRequest;
use Throwable;

/**
 * محرك التنفيذ الرئيسي لمنصة الذكاء الاصطناعي — إدارة استدعاء النماذج وسلاسل التتابع الراجع (System Fallback Pipeline) والسياسات والتكلفة
 */
class ExecutionEngine implements ExecutionEngineInterface
{
    public function __construct(
        protected AiRouterInterface     $router,
        protected SecretVaultInterface  $vault,
        protected PolicyEngineInterface $policyEngine,
        protected CostEngineInterface   $costEngine,
    ) {}

    public function run(ExecutionRequestDTO $request, int $attempts = 0): ExecutionResultDTO
    {
        $ulid = (string) Str::ulid();
        Log::info("[AI Milestone Trace] [Execution Engine Enter] ULID: {$ulid}, Capability: {$request->capabilityKey}, CompanyID: {$request->companyId}");

        // 1. تقييم السياسة قبل التنفيذ
        $policyDecision = $this->policyEngine->evaluate($request, $request->requestedBy ?? 0, []);
        if ($policyDecision->isDenied()) {
            Log::warning("[AI Milestone Trace] [Execution Engine Denied] Reason: {$policyDecision->reason}");
            return ExecutionResultDTO::failure(
                $policyDecision->errorCode ?? 'POLICY_DENIED',
                $policyDecision->reason ?? 'الطلب محجوب حسب سياسات الأمان',
                0,
                'PolicyEngine',
                'BusinessPolicy'
            );
        }

        // 2. التحقق من الميزانية للتكلفة
        if (!$this->costEngine->checkBudget($request->companyId ?? 1, null, null)) {
            Log::warning("[AI Milestone Trace] [Execution Engine Budget Exceeded] CompanyID: {$request->companyId}");
            return ExecutionResultDTO::failure('BUDGET_EXCEEDED', 'تم تجاوز ميزانية التكلفة المحددة');
        }

        $executionRecord = AiExecutionRequest::firstOrCreate(
            ['ulid' => $ulid],
            [
                'company_id' => $request->companyId ?? 1,
                'status' => 'pending',
                'capability_key' => $request->capabilityKey,
                'source_type' => $request->sourceType ?? 'direct'
            ]
        );

        if ($executionRecord->status === 'cancelled') {
            return ExecutionResultDTO::failure('CANCELLED', 'تم إلغاء الطلب');
        }

        $executionRecord->update(['status' => 'processing']);

        $capEnum = \Modules\AiPlatform\Enums\Capability::tryFrom($request->capabilityKey) ?? \Modules\AiPlatform\Enums\Capability::TextGenerate;

        // 3. جلب جميع الحسابات المتاحة المؤهلة وفق السياسة لتنفيذ التتابع السلسلي (Sequential Fallback Pipeline)
        $accounts = $this->router->selectAll($capEnum, $request->companyId, $request->options ?? [], 'priority');

        if ($accounts->isEmpty()) {
            $executionRecord->update(['status' => 'failed', 'error' => 'لا يوجد حساب مفاتيح ذكاء اصطناعي مفعل متاح للنظام بـ Execution Policy المطابقة']);
            return ExecutionResultDTO::failure('NO_ACCOUNT_AVAILABLE', 'لا يوجد حساب ذكاء اصطناعي متاح للنظام');
        }

        // 4. تجهيز نص القالب والطلب والمتغيرات من خلال PromptEngine أو raw_prompt
        $promptText = '';
        if (!empty($request->promptKey)) {
            try {
                /** @var \Modules\AiPlatform\Contracts\PromptEngineInterface $promptEngine */
                $promptEngine = app(\Modules\AiPlatform\Contracts\PromptEngineInterface::class);
                $promptText = $promptEngine->build(
                    $request->promptKey,
                    $request->companyId ?? 1,
                    $request->promptVariables ?? []
                );
            } catch (Throwable $e) {
                Log::warning("[Execution Engine] PromptEngine build failed for key '{$request->promptKey}': " . $e->getMessage());
            }
        }

        if (empty($promptText)) {
            $promptText = $request->promptVariables['raw_prompt'] ?? '';
        }

        if (empty($promptText)) {
            $productName = $request->promptVariables['product_name'] ?? '';
            $features = $request->promptVariables['features'] ?? '';
            $promptText = "أنت كاتب محتوى احترافي لمتجر إلكتروني.\nالمطلوب إنشاء وصف احترافي دقيق لمنتج باسم {$productName} ومواصفات {$features} ليتم حفظه مباشرة في قاعدة بيانات المتجر بدون أي علامات ماركدوان أو عناوين أو عبارات تمهيدية.";
        }

        $traceId = 'AI-' . date('Ymd') . '-' . substr($ulid, -8);
        $lastError = 'فشل تنفيذ جميع النماذج والحسابات المؤهلة بالنظام';

        // 5. دورة المحاولات المتتابعة عبر كافة النماذج والحسابات المؤهلة (System Model Fallback Pipeline)
        foreach ($accounts as $index => $account) {
            try {
                $activeModel = \Modules\AiPlatform\Models\AiModel::where('ai_provider_id', $account->ai_provider_id)
                    ->where('is_active', true)
                    ->first();
                $selectedModelSlug = $activeModel?->model_id ?? 'gemini-1.5-flash';

                $apiKey = $this->vault->decrypt($account->api_key_encrypted ?? '');
                $driverClass = $account->provider?->driver_class ?? \Modules\AiPlatform\Drivers\GeminiDriver::class;

                if (!class_exists($driverClass)) {
                    $driverClass = \Modules\AiPlatform\Drivers\GeminiDriver::class;
                }

                /** @var \Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface $driver */
                $driver = app($driverClass);

                $options = array_merge($request->options ?? [], [
                    'model'    => $selectedModelSlug,
                    'model_id' => $selectedModelSlug,
                ]);

                $baseUrl = $account->custom_base_url;

                Log::info("[Execution Engine] Attempting execution using Account #{$account->id} ({$account->label}) with Model {$selectedModelSlug} [System Attempt #" . ($index + 1) . "]");

                $startTime = microtime(true);
                $driverResponse = $driver->execute($promptText, $options, $apiKey, $baseUrl);
                $latency = (int) ((microtime(true) - $startTime) * 1000);

                if ($driverResponse->successful) {
                    $executionRecord->update(['status' => 'completed']);
                    $this->router->reportSuccess($account->id, $driverResponse->inputTokens + $driverResponse->outputTokens);
                    return ExecutionResultDTO::success(
                        $driverResponse->content,
                        null,
                        $driverResponse->inputTokens,
                        $driverResponse->outputTokens,
                        $driverResponse->inputTokens + $driverResponse->outputTokens,
                        $driverResponse->totalCost,
                        $latency,
                        $traceId
                    );
                } else {
                    $lastError = $driverResponse->errorMessage ?? 'فشل استجابة المحرك';
                    $this->router->reportFailure($account->id, $driverResponse->errorCode ?? 'EXECUTION_FAILED');
                    Log::warning("[Execution Engine] Account #{$account->id} failed: {$lastError}. Failing over to next system model in pipeline...");
                }

            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                $this->router->reportFailure($account->id, 'EXCEPTION');
                Log::warning("[Execution Engine] Exception on Account #{$account->id}: {$lastError}. Failing over to next system model in pipeline...");
            }
        }

        // 6. في حال فشل جميع نماذج النظام: تنفيذ سيناريو الفشل وتسجيل النتيجة دون انهيار النظام
        $executionRecord->update(['status' => 'failed', 'error' => $lastError]);
        return ExecutionResultDTO::failure(
            'ALL_SYSTEM_MODELS_FAILED',
            "تعذر تنفيذ الطلب عبر جميع نماذج وحسابات النظام المتاحة: {$lastError}",
            0,
            'ExecutionEngine',
            'System',
            $traceId
        );
    }

    public function dispatch(ExecutionRequestDTO $request): string
    {
        $ulid = (string) Str::ulid();
        AiExecutionRequest::create([
            'ulid' => $ulid,
            'company_id' => $request->companyId ?? 1,
            'status' => 'pending',
            'capability_key' => $request->capabilityKey,
            'source_type' => $request->sourceType ?? 'direct',
        ]);
        return $ulid;
    }

    public function stream(ExecutionRequestDTO $request): \Generator
    {
        $ulid = (string) Str::ulid();
        Log::info("[AI Milestone Trace] [Execution Engine Stream Enter] ULID: {$ulid}");

        $capEnum = \Modules\AiPlatform\Enums\Capability::tryFrom($request->capabilityKey) ?? \Modules\AiPlatform\Enums\Capability::TextGenerate;
        $route = $this->router->select($capEnum, $request->companyId ?? 1, $request->options ?? [], 'priority');

        if (!$route) {
            yield 'خطأ: لا يوجد حساب متاح للخدمة';
            return;
        }

        $account = \Modules\AiPlatform\Models\AiProviderAccount::with('provider')->find($route->accountId);
        if (!$account) {
            yield 'خطأ: حساب غير موجود';
            return;
        }

        $apiKey = $this->vault->decrypt($account->api_key_encrypted ?? '');
        $driverClass = $account->provider?->driver_class ?? \Modules\AiPlatform\Drivers\GeminiDriver::class;

        if (!class_exists($driverClass)) {
            $driverClass = \Modules\AiPlatform\Drivers\GeminiDriver::class;
        }

        /** @var \Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface $driver */
        $driver = app($driverClass);

        $promptText = $request->promptVariables['raw_prompt'] ?? "استجابة للبث المباشر";
        $options = array_merge($request->options ?? [], [
            'model'    => $route->modelId ?? 'gemini-1.5-flash',
            'model_id' => $route->modelId ?? 'gemini-1.5-flash',
        ]);

        $generator = $driver->stream($promptText, $options, $apiKey, $account->custom_base_url);
        foreach ($generator as $chunk) {
            yield $chunk;
        }
    }

    public function cancel(string $requestUlid): bool
    {
        $record = AiExecutionRequest::where('ulid', $requestUlid)->first();
        if ($record && $record->status !== 'completed') {
            $record->update(['status' => 'cancelled']);
            Log::info("[AI Milestone Trace] [Execution Engine Cancel] Request ULID: {$requestUlid} cancelled.");
            return true;
        }
        return false;
    }

    public function status(string $requestUlid): array
    {
        $record = AiExecutionRequest::where('ulid', $requestUlid)->first();
        if (!$record) {
            return ['status' => 'not_found'];
        }
        return [
            'request_ulid' => $record->ulid,
            'status' => $record->status,
            'error' => $record->error,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }
}

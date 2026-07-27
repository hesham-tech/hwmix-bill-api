<?php

namespace Modules\AiPlatform\Engines;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Router\AiRouterInterface;
use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\Contracts\Security\SecretVaultInterface;
use Modules\AiPlatform\Plugins\AiPlatformPluginRegistry;
use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;
use Modules\AiPlatform\Jobs\ExecuteAiRequestJob;
use Modules\AiPlatform\Models\AiExecutionRequest;
use Modules\AiPlatform\Models\AiExecutionResult;
use Modules\AiPlatform\Models\AiPrompt;
use Throwable;

// المحرك المركزي لتنفيذ طلبات الذكاء الاصطناعي وإدارتها تزامناً وتتابعاً
class ExecutionEngine implements ExecutionEngineInterface
{
    public function __construct(
        protected AiRouterInterface $router,
        protected SecretVaultInterface $vault,
        protected AiPlatformPluginRegistry $registry
    ) {}

    public function run(ExecutionRequestDTO $request, int $attempts = 0): ExecutionResultDTO
    {
        $ulid = $request->ulid ?? (string) Str::ulid();
        $request->ulid = $ulid;

        $executionRecord = AiExecutionRequest::firstOrCreate(
            ['ulid' => $ulid],
            [
                'company_id' => $request->companyId,
                'status' => 'processing',
                'payload' => $request->toArray(),
                'queued' => false,
                'capability_key' => $request->capabilityKey,
                'source_type' => $request->sourceType ?? 'direct'
            ]
        );

        if ($executionRecord->status === 'cancelled') {
            return ExecutionResultDTO::failure('CANCELLED', 'تم إلغاء الطلب');
        }

        $executionRecord->update(['status' => 'processing']);

        $capEnum = \Modules\AiPlatform\Enums\Capability::tryFrom($request->capabilityKey) ?? \Modules\AiPlatform\Enums\Capability::TextGenerate;
        $route = $this->router->select($capEnum, $request->companyId, $request->options ?? [], 'priority');

        if (!$route) {
            $executionRecord->update(['status' => 'failed', 'error' => 'لا يوجد حساب ذكاء اصطناعي متاح للشركة']);
            return ExecutionResultDTO::failure('NO_ACCOUNT_AVAILABLE', 'لا يوجد حساب ذكاء اصطناعي متاح للشركة');
        }

        $account = \Modules\AiPlatform\Models\AiProviderAccount::with('provider')->find($route->accountId);
        if (!$account) {
            $executionRecord->update(['status' => 'failed', 'error' => 'حساب غير موجود']);
            return ExecutionResultDTO::failure('ACCOUNT_NOT_FOUND', 'حساب غير موجود');
        }

        try {
            $apiKey = $this->vault->decrypt($account->api_key_encrypted ?? '');
            $driverClass = $account->provider?->driver_class ?? \Modules\AiPlatform\Drivers\GeminiDriver::class;
            
            if (!class_exists($driverClass)) {
                $driverClass = \Modules\AiPlatform\Drivers\GeminiDriver::class;
            }

            /** @var \Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface $driver */
            $driver = app($driverClass);

            // تجهيز نص القالب والطلب والمتغيرات من خلال المحرك المعماري المخصص PromptEngine
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
                } catch (\Throwable $e) {
                    Log::warning("[Execution Engine] PromptEngine build failed for key '{$request->promptKey}': " . $e->getMessage());
                }
            }

            if (empty($promptText)) {
                $productName = $request->promptVariables['product_name'] ?? '';
                $features = $request->promptVariables['features'] ?? '';
                $promptText = "أنت كاتب محتوى احترافي لمتجر إلكتروني.\nالمطلوب إنشاء وصف احترافي دقيق لمنتج باسم {$productName} ومواصفات {$features} ليتم حفظه مباشرة في قاعدة بيانات المتجر بدون أي علامات ماركدوان أو عناوين أو عبارات تمهيدية.";
            }

            $options = array_merge($request->options ?? [], [
                'model'    => $route->modelId ?? 'gemini-flash-latest',
                'model_id' => $route->modelId ?? 'gemini-flash-latest',
            ]);

            $traceId = 'AI-' . date('Ymd') . '-' . substr($ulid, -8);
            $baseUrl = $account->custom_base_url;

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
                $executionRecord->update(['status' => 'failed', 'error' => $driverResponse->errorMessage]);
                return ExecutionResultDTO::failure(
                    $driverResponse->errorCode ?? 'AI_EXECUTION_FAILED',
                    $driverResponse->errorMessage ?? 'فشل تنفيذ الطلب',
                    $latency,
                    $driverResponse->errorSource ?? 'Driver',
                    $driverResponse->errorType ?? 'System',
                    $traceId
                );
            }
            
        } catch (Throwable $e) {
            $this->router->reportFailure($account->id, $e->getMessage());
            
            if ($attempts < 1) { // Failover Attempt
                return $this->run($request, $attempts + 1);
            }
            
            $executionRecord->update(['status' => 'failed', 'error' => $e->getMessage()]);
            
            return ExecutionResultDTO::failure('EXECUTION_FAILED', $e->getMessage());
        }
    }

    public function dispatch(ExecutionRequestDTO $request): string
    {
        $ulid = (string) Str::ulid();
        $request->ulid = $ulid;
        
        AiExecutionRequest::create([
            'ulid' => $ulid,
            'company_id' => $request->companyId,
            'status' => 'pending',
            'payload' => $request->toArray(),
            'queued' => true,
            'capability_key' => $request->capabilityKey,
            'source_type' => $request->sourceType ?? 'direct'
        ]);
        
        ExecuteAiRequestJob::dispatch($ulid);
        
        return $ulid;
    }

    public function stream(ExecutionRequestDTO $request): \Generator
    {
        $ulid = $request->ulid ?? (string) Str::ulid();
        
        $route = $this->router->route($request);

        if (!$route) {
            yield 'error: لا يوجد حساب ذكاء اصطناعي متاح للشركة';
            return;
        }

        try {
            $apiKey = $this->vault->decrypt($route->account->api_key);
            $driver = $this->registry->getDriver($route->provider->code);
            
            yield from $driver->stream($request, $apiKey, $route->model->code);
            
            $this->router->reportSuccess($route->account->id);
        } catch (Throwable $e) {
            $this->router->reportFailure($route->account->id, $e->getMessage());
            yield 'error: ' . $e->getMessage();
        }
    }

    public function cancel(string $requestUlid): bool
    {
        $updated = AiExecutionRequest::where('ulid', $requestUlid)->update(['status' => 'cancelled']);
        
        return $updated > 0;
    }

    public function status(string $requestUlid): array
    {
        $record = AiExecutionRequest::with('result')->where('ulid', $requestUlid)->first();
        
        if (!$record) {
            return [];
        }
        
        return [
            'status' => $record->status,
            'result' => $record->result ? [
                'content' => $record->result->content,
                'usage' => $record->result->usage,
                'latency' => $record->result->latency_ms,
            ] : null,
        ];
    }
}

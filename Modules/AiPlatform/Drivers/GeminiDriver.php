<?php

// كلاس للتعامل مع واجهة برمجة تطبيقات Google Gemini وتنفيذ العمليات المطلوبة

namespace Modules\AiPlatform\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface;
use Modules\AiPlatform\Enums\ProviderType;
use Modules\AiPlatform\Enums\Capability;
use Modules\AiPlatform\Enums\AiErrorCode;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

class GeminiDriver implements ProviderDriverInterface
{
    // النماذج الوحيدة التي تعمل مع مفاتيح AQ. format (aliases ثابتة)
    protected array $fallbackModels = [
        'gemini-flash-latest',
        'gemini-pro-latest',
    ];

    public function getName(): string { return 'gemini'; }

    public function getType(): ProviderType { return ProviderType::Llm; }

    public function supports(Capability $capability): bool
    {
        return in_array($capability->value, $this->capabilities());
    }

    public function capabilities(): array
    {
        return ['text.generate', 'text.summarize', 'text.translate', 'image.analyze'];
    }

    public function execute(
        string  $builtPrompt,
        array   $options,
        string  $apiKey,
        ?string $baseUrl = null
    ): ExecutionResultDTO {
        $startTime = microtime(true);

        // الخطوة 1: تحديد النموذج المطلوب من الـ options
        $requestedModel = $options['model_id'] ?? $options['model'] ?? 'gemini-1.5-flash';
        Log::info("[Gemini Driver] [Step 1] Requested model: {$requestedModel}");

        // الخطوة 2: تطبيع الاسم — تحويل أي اسم قديم/2.x إلى gemini-1.5-flash
        $model = $this->normalizeModelName($requestedModel);
        Log::info("[Gemini Driver] [Step 2] Normalized model: {$model}");

        // الخطوة 3: محاولة النموذج المطلوب أولاً، ثم Failover بالترتيب
        $modelsToTry = array_unique(array_merge([$model], $this->fallbackModels));
        Log::info("[Gemini Driver] [Step 3] Will try models in order: " . implode(', ', $modelsToTry));

        $lastResult = null;
        foreach ($modelsToTry as $attempt) {
            Log::info("[Gemini Driver] [Attempting] Model: {$attempt}");
            $result = $this->callGeminiApi($builtPrompt, $attempt, $apiKey, $baseUrl, $startTime);

            if ($result->successful) {
                Log::info("[Gemini Driver] [Success] Model: {$attempt}, Tokens: {$result->inputTokens}+{$result->outputTokens}");
                return $result;
            }

            $errorMsg = $result->errorMessage ?? '';
            Log::warning("[Gemini Driver] [Failed] Model: {$attempt}, Error: {$errorMsg}");

            // إذا كان الخطأ Quota أو مفتاح خاطئ — لا فائدة من المحاولة بنماذج أخرى
            if (
                str_contains($errorMsg, 'API key not valid') ||
                str_contains($errorMsg, 'PERMISSION_DENIED')
            ) {
                Log::error("[Gemini Driver] [Auth Error] Stopping failover. Error: {$errorMsg}");
                return $result;
            }

            // إذا كان Quota مستنفد على هذا النموذج — نجرب التالي
            $lastResult = $result;
        }

        // استنفدنا كل الاحتمالات
        Log::error("[Gemini Driver] [All Models Failed] Last error: " . ($lastResult?->errorMessage ?? 'Unknown'));
        return $lastResult ?? ExecutionResultDTO::failure(
            AiErrorCode::AI_DRIVER_NETWORK_ERROR->value,
            'فشلت جميع نماذج Google Gemini المتاحة',
            0,
            'Driver',
            'GoogleGemini'
        );
    }

    protected function callGeminiApi(
        string  $prompt,
        string  $model,
        string  $apiKey,
        ?string $baseUrl,
        float   $startTime
    ): ExecutionResultDTO {
        $baseEndpoint = $baseUrl ?: "https://generativelanguage.googleapis.com";
        $endpoint = "{$baseEndpoint}/v1beta/models/{$model}:generateContent?key={$apiKey}";

        Log::info("[Gemini Driver] [HTTP] POST {$baseEndpoint}/v1beta/models/{$model}:generateContent?key=***");

        try {
            $payload = [
                'contents' => [['parts' => [['text' => $prompt]]]]
            ];

            if (str_contains(strtolower($prompt), 'json') || !empty($options['json_mode']) || !empty($options['response_mime_type'])) {
                $payload['generationConfig'] = [
                    'responseMimeType' => 'application/json'
                ];
            }

            $response = Http::timeout(20)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            $latency = (int)((microtime(true) - $startTime) * 1000);
            Log::info("[Gemini Driver] [HTTP Response] Status: {$response->status()}, Latency: {$latency}ms");

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                // استخراج النص النقي في حال إرجاع JSON أو Structured Output
                $cleanJsonText = trim($text);
                if (preg_match('/```json\s*(.*?)\s*```/s', $cleanJsonText, $matches)) {
                    $cleanJsonText = $matches[1];
                }
                $decoded = json_decode(trim($cleanJsonText), true);
                if (is_array($decoded) && !empty($decoded['description'])) {
                    $text = $decoded['description'];
                } elseif (is_array($decoded) && !empty($decoded['content'])) {
                    $text = $decoded['content'];
                } else {
                    // تنظيف العبارات الحوارية التمهيدية وفواصل الماركدوان والعناوين الشارحة
                    $text = preg_replace('/^(\s*[\-\*]{3,}\s*|\s*\*\*?(العنوان|الوصف):\*\*?\s*)+/u', '', trim($text));
                    $text = preg_replace('/^(إليك|إليك عدة خيارات|إليك وصف|إليك تفاصيل|هذا وصف|عزيزي المستخدم)[^\n]*\n+/u', '', trim($text));
                    $text = preg_replace('/(\n\s*|\n)\*\s*\(نصيحة[^\)]*\)\s*\.?$/u', '', $text);
                }
                $text = trim($text);

                $usage = $data['usageMetadata'] ?? [];
                $promptTokens = $usage['promptTokenCount'] ?? 0;
                $completionTokens = $usage['candidatesTokenCount'] ?? 0;

                Log::info("[Gemini Driver] [Parsed] TextLength: " . strlen($text) . ", Tokens: {$promptTokens}+{$completionTokens}");

                return ExecutionResultDTO::success(
                    $text, $data, $promptTokens, $completionTokens,
                    $promptTokens + $completionTokens, 0.0001, $latency
                );
            }

            $errJson = $response->json();
            $errorMessage = $errJson['error']['message'] ?? $response->body() ?: 'Unknown error';
            Log::warning("[Gemini Driver] [API Error] Model: {$model}, Message: {$errorMessage}");

            $errorCode = AiErrorCode::AI_DRIVER_NETWORK_ERROR->value;
            if (str_contains($errorMessage, 'API key not valid'))        $errorCode = AiErrorCode::AI_PROVIDER_AUTH_FAILED->value;
            if (str_contains($errorMessage, 'exceeded your current quota')) $errorCode = AiErrorCode::AI_PROVIDER_QUOTA_EXCEEDED->value;

            return ExecutionResultDTO::failure($errorCode, $errorMessage, $latency, 'Driver', 'GoogleGemini');

        } catch (\Exception $e) {
            $latency = (int)((microtime(true) - $startTime) * 1000);
            Log::error("[Gemini Driver] [Exception] Model: {$model}, Exception: {$e->getMessage()}");
            return ExecutionResultDTO::failure(
                AiErrorCode::AI_DRIVER_NETWORK_ERROR->value,
                $e->getMessage(), $latency, 'Driver', 'System'
            );
        }
    }

    protected function normalizeModelName(string $rawModel): string
    {
        // النماذج الوحيدة المضمونة مع مفاتيح AQ. format
        $knownGoodModels = ['gemini-flash-latest', 'gemini-pro-latest'];

        if (in_array($rawModel, $knownGoodModels)) {
            return $rawModel;
        }

        // كل الأسماء الأخرى (gemini-2.x, gemini-1.5-x, إلخ) → gemini-flash-latest
        Log::warning("[Gemini Driver] [normalizeModelName] Remapping '{$rawModel}' → gemini-flash-latest");
        return 'gemini-flash-latest';
    }

    public function stream(string $builtPrompt, array $options, string $apiKey, ?string $baseUrl = null): \Generator
    {
        $model = $this->normalizeModelName($options['model_id'] ?? $options['model'] ?? 'gemini-1.5-flash');
        $endpoint = ($baseUrl ?: "https://generativelanguage.googleapis.com") . "/v1beta/models/{$model}:streamGenerateContent?key={$apiKey}";

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->send('POST', $endpoint, [
            'json'   => ['contents' => [['parts' => [['text' => $builtPrompt]]]]],
            'stream' => true,
        ]);

        $body = $response->toPsrResponse()->getBody();
        while (!$body->eof()) yield $body->read(1024);
    }

    public function healthCheck(string $apiKey, ?string $baseUrl = null): bool
    {
        $endpoint = ($baseUrl ?: "https://generativelanguage.googleapis.com") . "/v1beta/models?key={$apiKey}";
        return Http::timeout(10)->get($endpoint)->successful();
    }
}

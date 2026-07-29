<?php

// كلاس للتعامل مع واجهة برمجة تطبيقات Groq Cloud API (Llama 3.1 & Open Source Models)

namespace Modules\AiPlatform\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface;
use Modules\AiPlatform\Enums\ProviderType;
use Modules\AiPlatform\Enums\Capability;
use Modules\AiPlatform\Enums\AiErrorCode;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

class GroqDriver implements ProviderDriverInterface
{
    public function getName(): string
    {
        return 'groq';
    }

    public function getType(): ProviderType
    {
        return ProviderType::Llm;
    }

    public function supports(Capability $capability): bool
    {
        return in_array($capability->value, $this->capabilities());
    }

    public function capabilities(): array
    {
        return [
            'text.generate',
            'text.summarize',
            'text.translate',
            'text.analyze',
        ];
    }

    public function execute(
        string  $builtPrompt,
        array   $options,
        string  $apiKey,
        ?string $baseUrl = null
    ): ExecutionResultDTO {
        $startTime = microtime(true);
        $model = $options['model_id'] ?? $options['model'] ?? 'llama-3.1-8b-instant';
        $baseEndpoint = $baseUrl ?: "https://api.groq.com/openai/v1";
        $endpoint = "{$baseEndpoint}/chat/completions";

        Log::info("[Groq Driver] [HTTP] POST {$endpoint} for model: {$model}");

        try {
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $builtPrompt]
                ],
                'temperature' => 0.7,
            ];

            if (str_contains(strtolower($builtPrompt), 'json') || !empty($options['json_mode']) || !empty($options['response_mime_type'])) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::timeout(25)->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->post($endpoint, $payload);

            $latency = (int) ((microtime(true) - $startTime) * 1000);
            Log::info("[Groq Driver] [HTTP Response] Status: {$response->status()}, Latency: {$latency}ms");

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';

                // استخراج وتنظيف النص في حال وجود Structured Output أو JSON
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
                    $text = preg_replace('/^(\s*[\-\*]{3,}\s*|\s*\*\*?(العنوان|الوصف):\*\*?\s*)+/u', '', trim($text));
                    $text = preg_replace('/^(إليك|إليك عدة خيارات|إليك وصف|إليك تفاصيل|هذا وصف|عزيزي المستخدم)[^\n]*\n+/u', '', trim($text));
                    $text = preg_replace('/(\n\s*|\n)\*\s*\(نصيحة[^\)]*\)\s*\.?$/u', '', $text);
                }
                $text = trim($text);

                $usage = $data['usage'] ?? [];
                $promptTokens = $usage['prompt_tokens'] ?? 0;
                $completionTokens = $usage['completion_tokens'] ?? 0;
                $totalTokens = $usage['total_tokens'] ?? ($promptTokens + $completionTokens);

                // احتساب التكلفة الدقيقة لموديل Groq Llama 3.1 (0.05$ لكل 1M توكين دخل، 0.08$ لكل 1M توكين خرج)
                $inputPricePer1k = 0.00005;
                $outputPricePer1k = 0.00008;
                $cost = (($promptTokens / 1000) * $inputPricePer1k) + (($completionTokens / 1000) * $outputPricePer1k);

                Log::info("[Groq Driver] [Success] Model: {$model}, Tokens: {$promptTokens}+{$completionTokens}, Cost: {$cost}");

                return ExecutionResultDTO::success(
                    $text,
                    $data,
                    $promptTokens,
                    $completionTokens,
                    $totalTokens,
                    $cost,
                    $latency
                );
            }

            $errJson = $response->json();
            $errorMessage = $errJson['error']['message'] ?? $response->body() ?: 'فشلت الاستجابة من Groq API';
            Log::warning("[Groq Driver] [API Error] Model: {$model}, Status: {$response->status()}, Message: {$errorMessage}");

            $errorCode = AiErrorCode::AI_DRIVER_NETWORK_ERROR->value;
            if (str_contains(strtolower($errorMessage), 'invalid api key') || str_contains(strtolower($errorMessage), 'unauthorized') || $response->status() === 401) {
                $errorCode = AiErrorCode::AI_PROVIDER_AUTH_FAILED->value;
            } elseif (str_contains(strtolower($errorMessage), 'rate limit') || str_contains(strtolower($errorMessage), 'quota') || $response->status() === 429) {
                $errorCode = AiErrorCode::AI_PROVIDER_QUOTA_EXCEEDED->value;
            }

            return ExecutionResultDTO::failure(
                $errorCode,
                $errorMessage,
                $latency,
                'Driver',
                'Groq'
            );
        } catch (\Exception $e) {
            $latency = (int) ((microtime(true) - $startTime) * 1000);
            Log::error("[Groq Driver] [Exception] Model: {$model}, Message: {$e->getMessage()}");
            return ExecutionResultDTO::failure(
                AiErrorCode::AI_DRIVER_NETWORK_ERROR->value,
                $e->getMessage(),
                $latency,
                'Driver',
                'Groq'
            );
        }
    }

    public function stream(
        string  $builtPrompt,
        array   $options,
        string  $apiKey,
        ?string $baseUrl = null
    ): \Generator {
        $model = $options['model_id'] ?? $options['model'] ?? 'llama-3.1-8b-instant';
        $baseEndpoint = $baseUrl ?: "https://api.groq.com/openai/v1";
        $endpoint = "{$baseEndpoint}/chat/completions";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->send('POST', $endpoint, [
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $builtPrompt]
                ],
                'stream' => true,
            ],
            'stream' => true,
        ]);

        $body = $response->toPsrResponse()->getBody();
        while (!$body->eof()) {
            yield $body->read(1024);
        }
    }

    public function healthCheck(string $apiKey, ?string $baseUrl = null): bool
    {
        $baseEndpoint = $baseUrl ?: "https://api.groq.com/openai/v1";
        $endpoint = "{$baseEndpoint}/models";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get($endpoint);

        return $response->successful();
    }
}

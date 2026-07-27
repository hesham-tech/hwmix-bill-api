<?php

// كلاس للتعامل مع واجهة برمجة تطبيقات OpenAI (ChatGPT)

namespace Modules\AiPlatform\Drivers;

use Illuminate\Support\Facades\Http;
use Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface;
use Modules\AiPlatform\Enums\ProviderType;
use Modules\AiPlatform\Enums\Capability;
use Modules\AiPlatform\Enums\AiErrorCode;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

class OpenAiDriver implements ProviderDriverInterface
{
    public function getName(): string
    {
        return 'openai';
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
            'image.analyze',
        ];
    }

    public function execute(
        string  $builtPrompt,
        array   $options,
        string  $apiKey,
        ?string $baseUrl = null
    ): ExecutionResultDTO {
        $startTime = microtime(true);
        $model = $options['model_id'] ?? $options['model'] ?? 'gpt-4o-mini';
        $endpoint = ($baseUrl ?: "https://api.openai.com/v1") . "/chat/completions";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $builtPrompt]
                ]
            ]);

            $latency = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['content']['parts'][0]['text'] ?? $data['choices'][0]['message']['content'] ?? '';
                
                $usage = $data['usage'] ?? [];
                $promptTokens = $usage['prompt_tokens'] ?? 0;
                $completionTokens = $usage['completion_tokens'] ?? 0;
                $totalTokens = $usage['total_tokens'] ?? ($promptTokens + $completionTokens);
                $cost = 0.00015;
                
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
            $errorMessage = $errJson['error']['message'] ?? $response->body() ?: 'فشل الطلب من OpenAI API';
            $errorCode = AiErrorCode::AI_DRIVER_NETWORK_ERROR->value;

            if (str_contains($errorMessage, 'Incorrect API key')) {
                $errorCode = AiErrorCode::AI_PROVIDER_AUTH_FAILED->value;
            }

            return ExecutionResultDTO::failure(
                $errorCode,
                $errorMessage,
                $latency,
                'Driver',
                'OpenAI'
            );
        } catch (\Exception $e) {
            $latency = (int) ((microtime(true) - $startTime) * 1000);
            return ExecutionResultDTO::failure(
                AiErrorCode::AI_DRIVER_NETWORK_ERROR->value,
                $e->getMessage(),
                $latency,
                'Driver',
                'System'
            );
        }
    }

    public function stream(
        string  $builtPrompt,
        array   $options,
        string  $apiKey,
        ?string $baseUrl = null
    ): \Generator {
        $model = $options['model_id'] ?? $options['model'] ?? 'gpt-4o-mini';
        $endpoint = ($baseUrl ?: "https://api.openai.com/v1") . "/chat/completions";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
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
        $endpoint = ($baseUrl ?: "https://api.openai.com/v1") . "/models";
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get($endpoint);
        
        return $response->successful();
    }
}

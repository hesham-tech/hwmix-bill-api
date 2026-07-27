<?php

namespace Modules\AiPlatform\Tests\Fixtures;

use Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface;
use Modules\AiPlatform\Enums\ProviderType;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;
use Modules\AiPlatform\DTOs\PromptDTO;

/**
 * كلاس تجريبي لـ Gemini Driver
 */
class FakeGeminiDriver implements ProviderDriverInterface
{
    public function getName(): string
    {
        return 'gemini-fake';
    }

    public function getType(): ProviderType
    {
        return ProviderType::Llm;
    }

    public function supports(string $feature): bool
    {
        return true;
    }

    public function execute(PromptDTO $prompt, array $options = []): ExecutionResultDTO
    {
        return new ExecutionResultDTO(
            successful: true,
            content: 'محتوى تجريبي من Gemini Fake',
            inputTokens: 10,
            outputTokens: 20,
            totalCost: 0.0001,
            latencyMs: 50
        );
    }

    public function stream(PromptDTO $prompt, array $options = []): \Generator
    {
        yield 'كلمة1 ';
        yield 'كلمة2';
    }

    public function healthCheck(): bool
    {
        return true;
    }
}

<?php

namespace Modules\AiPlatform\Tests\Fixtures;

use Modules\AiPlatform\Contracts\Drivers\ProviderDriverInterface;
use Modules\AiPlatform\Enums\ProviderType;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;
use Modules\AiPlatform\DTOs\PromptDTO;

/**
 * كلاس تجريبي لـ OpenAI Driver
 */
class FakeOpenAiDriver implements ProviderDriverInterface
{
    public function getName(): string
    {
        return 'openai-fake';
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
            content: 'محتوى تجريبي من OpenAI Fake',
            inputTokens: 15,
            outputTokens: 30,
            totalCost: 0.0002,
            latencyMs: 60
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

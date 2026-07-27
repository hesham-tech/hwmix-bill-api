<?php

// عقد محرك التنفيذ المركزي — كل Agent وWorkflow يمر عبره
namespace Modules\AiPlatform\Contracts\Engines;

use Modules\AiPlatform\DTOs\ExecutionRequestDTO;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

interface ExecutionEngineInterface
{
    /** تنفيذ Synchronous */
    public function run(ExecutionRequestDTO $request): ExecutionResultDTO;

    /**
     * تنفيذ Async عبر Queue
     * يُعيد ULID الـ ExecutionRequest
     */
    public function dispatch(ExecutionRequestDTO $request): string;

    /**
     * Streaming
     * @return \Generator<string>
     */
    public function stream(ExecutionRequestDTO $request): \Generator;

    /** إلغاء طلب جارٍ */
    public function cancel(string $requestUlid): bool;

    /** حالة طلب Async */
    public function status(string $requestUlid): array;
}

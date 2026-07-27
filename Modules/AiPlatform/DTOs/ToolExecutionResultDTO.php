<?php

// نتيجة تنفيذ Tool
namespace Modules\AiPlatform\DTOs;

final class ToolExecutionResultDTO
{
    public function __construct(
        public readonly bool    $successful,
        public readonly mixed   $output,
        public readonly ?string $error       = null,
        public readonly int     $executionMs = 0,
    ) {}

    public static function success(mixed $output, int $ms = 0): self
    {
        return new self(true, $output, null, $ms);
    }

    public static function failure(string $error, int $ms = 0): self
    {
        return new self(false, null, $error, $ms);
    }
}

<?php

namespace Modules\AiPlatform\DTOs;

/**
 * نتيجة تنفيذ طلبات الذكاء الاصطناعي مع الدعم المعماري الكامل للـ Trace ID والتفصيل الدقيق للأخطاء
 */
final class ExecutionResultDTO
{
    public function __construct(
        public readonly bool    $successful,
        public readonly ?string $content,
        public readonly string  $contentType    = 'text', // 'text'|'json'|'image_url'|'audio_url'
        public readonly int     $inputTokens    = 0,
        public readonly int     $outputTokens   = 0,
        public readonly float   $totalCost      = 0.0,
        public readonly int     $latencyMs      = 0,
        public readonly ?array  $toolCalls      = null,
        public readonly ?string $errorCode      = null,
        public readonly ?string $errorMessage   = null,
        public readonly ?string $errorSource     = null, // 'Router'|'Vault'|'Driver'|'Engine'|'Database'
        public readonly ?string $errorType       = null, // 'Auth'|'Quota'|'Network'|'System'
        public readonly ?string $traceId         = null, // Correlation ID e.g. AI-20260726-ULID
        public readonly ?int    $modelId        = null,
        public readonly ?int    $accountId      = null,
        public readonly int     $attemptNumber  = 1,
    ) {}

    public static function success(
        string  $content,
        mixed   $rawResponse = null,
        int     $inputTokens = 0,
        int     $outputTokens = 0,
        int     $totalTokens = 0,
        float   $cost = 0.0,
        int     $latencyMs = 0,
        ?string $traceId = null
    ): self {
        return new self(
            successful:    true,
            content:       $content,
            contentType:   'text',
            inputTokens:   $inputTokens,
            outputTokens:  $outputTokens,
            totalCost:     $cost,
            latencyMs:     $latencyMs,
            traceId:       $traceId,
        );
    }

    public static function failure(
        string  $errorCode,
        string  $errorMessage,
        int     $latencyMs = 0,
        ?string $errorSource = 'Engine',
        ?string $errorType = 'System',
        ?string $traceId = null,
        int     $attemptNumber = 1
    ): self {
        return new self(
            successful:    false,
            content:       null,
            contentType:   'text',
            inputTokens:   0,
            outputTokens:  0,
            totalCost:     0.0,
            latencyMs:     $latencyMs,
            errorCode:     $errorCode,
            errorMessage:  $errorMessage,
            errorSource:   $errorSource,
            errorType:     $errorType,
            traceId:       $traceId,
            attemptNumber: $attemptNumber,
        );
    }
}

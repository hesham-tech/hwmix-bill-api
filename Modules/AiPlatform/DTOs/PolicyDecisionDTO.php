<?php

// قرار السياسة
namespace Modules\AiPlatform\DTOs;

final class PolicyDecisionDTO
{
    public function __construct(
        public readonly string  $decision,          // 'allow'|'deny'
        public readonly ?string $denyReason    = null,
        public readonly ?string $denyMessage   = null, // للمستخدم
        public readonly int     $evaluatedPolicies = 0,
        public readonly int     $evaluationMs  = 0,
    ) {}

    public function isAllowed(): bool
    {
        return $this->decision === 'allow';
    }

    public function isDenied(): bool
    {
        return $this->decision === 'deny';
    }

    public static function allow(int $evaluated = 0, int $ms = 0): self
    {
        return new self('allow', evaluatedPolicies: $evaluated, evaluationMs: $ms);
    }

    public static function deny(string $reason, string $message, int $evaluated = 0, int $ms = 0): self
    {
        return new self('deny', $reason, $message, $evaluated, $ms);
    }
}

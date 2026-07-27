<?php

// قرار اختيار الـ Router
namespace Modules\AiPlatform\DTOs;

final class RouterSelectionDTO
{
    public function __construct(
        public readonly int        $accountId,
        public readonly string|int $modelId,
        public readonly string     $reason,             // 'priority'|'cost'|'failover'
        public readonly array  $consideredAccounts,
        public readonly int    $decisionMs,
    ) {}
}

<?php
// كيان دومين يعبر عن مصدر الرسائل المعتمد في كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Entities;

use Modules\HwnixCash\Domain\Enums\WalletProvider;

class MessageSourceEntity
{
    public function __construct(
        public ?int $id,
        public int $companyId,
        public ?int $createdBy,
        public string $senderIdentifier,
        public WalletProvider $provider,
        public bool $isActive,
        public ?string $description
    ) {}
}

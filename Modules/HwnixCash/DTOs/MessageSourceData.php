<?php
// كائن نقل بيانات مصادر الرسائل المعتمدة لكاش هونكس.

namespace Modules\HwnixCash\DTOs;

class MessageSourceData
{
    public function __construct(
        public string $senderIdentifier,
        public string $provider = 'other',
        public bool $isActive = true,
        public ?string $description = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            senderIdentifier: $data['sender_identifier'],
            provider: $data['provider'] ?? 'other',
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            description: $data['description'] ?? null
        );
    }
}

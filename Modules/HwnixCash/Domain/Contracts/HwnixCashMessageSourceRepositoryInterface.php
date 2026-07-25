<?php
// واجهة مستودع بيانات مصادر الرسائل المعتمدة لكاش هونكس HwnixCash.

namespace Modules\HwnixCash\Domain\Contracts;

use Illuminate\Support\Collection;
use Modules\HwnixCash\Domain\Entities\MessageSourceEntity;
use Modules\HwnixCash\DTOs\MessageSourceData;

interface HwnixCashMessageSourceRepositoryInterface
{
    public function findById(int $id): ?MessageSourceEntity;

    public function findActiveByIdentifier(string $senderIdentifier, int $companyId): ?MessageSourceEntity;

    public function create(MessageSourceData $dto, int $companyId, int $userId): MessageSourceEntity;

    public function update(int $id, MessageSourceData $dto): ?MessageSourceEntity;

    public function delete(int $id): bool;

    public function getCompanySources(int $companyId): Collection;
}

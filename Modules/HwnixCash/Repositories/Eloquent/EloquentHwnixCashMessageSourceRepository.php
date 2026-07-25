<?php
// المستودع المادي لإدارة مصادر الرسائل المعتمدة باستخدام Eloquent.

namespace Modules\HwnixCash\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\HwnixCash\Domain\Contracts\HwnixCashMessageSourceRepositoryInterface;
use Modules\HwnixCash\Domain\Entities\MessageSourceEntity;
use Modules\HwnixCash\Domain\Enums\WalletProvider;
use Modules\HwnixCash\DTOs\MessageSourceData;
use Modules\HwnixCash\Models\HwnixCashMessageSource;

class EloquentHwnixCashMessageSourceRepository implements HwnixCashMessageSourceRepositoryInterface
{
    public function findById(int $id): ?MessageSourceEntity
    {
        $model = HwnixCashMessageSource::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findActiveByIdentifier(string $senderIdentifier, int $companyId): ?MessageSourceEntity
    {
        $model = HwnixCashMessageSource::where('company_id', $companyId)
            ->where('sender_identifier', $senderIdentifier)
            ->where('is_active', true)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function create(MessageSourceData $dto, int $companyId, int $userId): MessageSourceEntity
    {
        $source = HwnixCashMessageSource::create([
            'company_id' => $companyId,
            'created_by' => $userId,
            'sender_identifier' => $dto->senderIdentifier,
            'provider' => $dto->provider,
            'is_active' => $dto->isActive,
            'description' => $dto->description,
        ]);

        return $this->toEntity($source);
    }

    public function update(int $id, MessageSourceData $dto): ?MessageSourceEntity
    {
        $source = HwnixCashMessageSource::find($id);
        if (!$source) {
            return null;
        }

        $source->update([
            'sender_identifier' => $dto->senderIdentifier,
            'provider' => $dto->provider,
            'is_active' => $dto->isActive,
            'description' => $dto->description,
        ]);

        return $this->toEntity($source->fresh());
    }

    public function delete(int $id): bool
    {
        return (bool) HwnixCashMessageSource::where('id', $id)->delete();
    }

    public function getCompanySources(int $companyId): Collection
    {
        return HwnixCashMessageSource::where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    protected function toEntity(HwnixCashMessageSource $model): MessageSourceEntity
    {
        return new MessageSourceEntity(
            id: $model->id,
            companyId: $model->company_id,
            createdBy: $model->created_by,
            senderIdentifier: $model->sender_identifier,
            provider: $model->provider instanceof WalletProvider ? $model->provider : WalletProvider::from($model->provider),
            isActive: (bool) $model->is_active,
            description: $model->description
        );
    }
}

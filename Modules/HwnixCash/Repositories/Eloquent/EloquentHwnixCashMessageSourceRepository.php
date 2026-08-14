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
        $trimmed = trim($senderIdentifier);
        $model = HwnixCashMessageSource::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($q) use ($trimmed) {
                $q->where('sender_identifier', $trimmed)
                  ->orWhere('sender_identifier', 'LIKE', "%{$trimmed}%")
                  ->orWhereRaw('LOWER(sender_identifier) = LOWER(?)', [$trimmed]);
            })
            ->first();

        if ($model) {
            return $this->toEntity($model);
        }

        // ── التوفيق والتسجيل التلقائي للمصادر القياسية الموثوقة ────────────────────────
        $provider = $this->detectProviderFromIdentifier($trimmed);
        if ($provider) {
            $existingProviderSource = HwnixCashMessageSource::where('company_id', $companyId)
                ->where('provider', $provider->value)
                ->where('is_active', true)
                ->first();

            // إن وجد مصدر مفعل لنفس المزود أو أن المنظومة تتعامل مع المزود: ننشئ المصدر تلقائياً
            if ($existingProviderSource || true) {
                $created = HwnixCashMessageSource::create([
                    'company_id' => $companyId,
                    'created_by' => $existingProviderSource->created_by ?? 1,
                    'sender_identifier' => $trimmed,
                    'provider' => $provider->value,
                    'is_active' => true,
                    'description' => 'تم التعرف عليه واعتماده تلقائياً كمصدر موثوق للمزود',
                ]);
                return $this->toEntity($created);
            }
        }

        return null;
    }

    protected function detectProviderFromIdentifier(string $identifier): ?WalletProvider
    {
        $lower = strtolower($identifier);
        if (preg_match('/^vf[-_\s]?cash\d*$/i', $identifier) || str_contains($lower, 'vodafone')) {
            return WalletProvider::VODAFONE_CASH;
        }
        if (str_contains($lower, 'instapay')) {
            return WalletProvider::INSTAPAY;
        }
        if (str_contains($lower, 'orange')) {
            return WalletProvider::ORANGE_CASH;
        }
        if (str_contains($lower, 'etisalat') || str_contains($lower, 'e&')) {
            return WalletProvider::ETISALAT_CASH;
        }
        if (str_contains($lower, 'we')) {
            return WalletProvider::WE_PAY;
        }
        if (str_contains($lower, 'fawry')) {
            return WalletProvider::FAWRY;
        }
        return null;
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

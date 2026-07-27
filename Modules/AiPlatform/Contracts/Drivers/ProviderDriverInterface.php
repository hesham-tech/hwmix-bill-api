<?php

// عقد كل Driver — يُطبَّق من كل مزود ذكاء اصطناعي
namespace Modules\AiPlatform\Contracts\Drivers;

use Modules\AiPlatform\DTOs\ExecutionResultDTO;
use Modules\AiPlatform\Enums\Capability;
use Modules\AiPlatform\Enums\ProviderType;

interface ProviderDriverInterface
{
    /** اسم المزود الفريد — يجب أن يطابق ai_providers.key */
    public function getName(): string;

    /** نوع المزود */
    public function getType(): ProviderType;

    /** هل يدعم هذا الـ Capability؟ */
    public function supports(Capability $capability): bool;

    /** قائمة الـ Capabilities المدعومة */
    public function capabilities(): array;

    /**
     * تنفيذ طلب
     * Driver لا يعرف DB ولا يُشفّر/يُفكّك المفاتيح — يستقبل المفتاح جاهزاً
     */
    public function execute(
        string  $builtPrompt,
        array   $options,    // ['model_id'=>..., 'temperature'=>..., 'max_tokens'=>...]
        string  $apiKey,     // مُفكَّك التشفير
        ?string $baseUrl,
    ): ExecutionResultDTO;

    /**
     * Streaming — يُعيد Generator يُرسل القطع تدريجياً
     * @return \Generator<string>
     */
    public function stream(
        string  $builtPrompt,
        array   $options,
        string  $apiKey,
        ?string $baseUrl,
    ): \Generator;

    /**
     * فحص صحة الاتصال
     * يُعيد true عند النجاح
     */
    public function healthCheck(string $apiKey, ?string $baseUrl): bool;
}

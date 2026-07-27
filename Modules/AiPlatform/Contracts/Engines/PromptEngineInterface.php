<?php

// عقد محرك الـ Prompts
namespace Modules\AiPlatform\Contracts\Engines;

interface PromptEngineInterface
{
    /** بناء نص الـ Prompt النهائي */
    public function build(
        string $promptKey,
        int    $companyId,
        array  $variables,
        string $locale = 'ar',
    ): string;

    /** معاينة Prompt مع بيانات وهمية */
    public function preview(
        string $promptKey,
        int    $companyId,
        array  $variables,
    ): string;

    /** التحقق من اكتمال المتغيرات المطلوبة */
    public function validate(string $promptKey, int $companyId, array $variables): array;
}

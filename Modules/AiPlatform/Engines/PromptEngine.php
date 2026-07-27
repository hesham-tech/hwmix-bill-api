<?php

namespace Modules\AiPlatform\Engines;

use Modules\AiPlatform\Contracts\PromptEngineInterface;
use Modules\AiPlatform\Models\AiPrompt;

/**
 * محرك استخراج وتجهيز قوالب الـ Prompts واستبدال المتغيرات.
 */
class PromptEngine implements PromptEngineInterface
{
    public function build(string $promptKey, int $companyId, array $variables, string $locale = 'ar'): string
    {
        $prompt = AiPrompt::where('key', $promptKey)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();

        $version = $prompt->versions()
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->firstOrFail();

        // استخراج المحتوى الخاص بالنسخة الفعالة
        $template = $version->template;

        return $this->replaceVariables($template, $variables);
    }

    public function preview(string $promptKey, int $companyId, array $variables): string
    {
        return $this->build($promptKey, $companyId, $variables);
    }

    public function validate(string $promptKey, int $companyId, array $variables): bool
    {
        $prompt = AiPrompt::where('key', $promptKey)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();

        $version = $prompt->versions()
            ->where('is_active', true)
            ->orderBy('version_number', 'desc')
            ->firstOrFail();

        $requiredVariables = $version->variables()->pluck('name')->toArray();

        foreach ($requiredVariables as $requiredVar) {
            if (!array_key_exists($requiredVar, $variables)) {
                return false;
            }
        }

        return true;
    }

    private function replaceVariables(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = $value;
            $replacements['{{ ' . $key . ' }}'] = $value;
        }

        return strtr($template, $replacements);
    }
}

<?php
// سجل مكونات ومحللات الذكاء الاصطناعي المنصية القابلة للإضافة المباشرة دون تعديل المحرك الرئيسي.

namespace Modules\AiPlatform\Engines\AnalysisEngine;

use InvalidArgumentException;
use Modules\AiPlatform\Contracts\Analysis\AnalyzerInterface;

class AnalyzerRegistry
{
    /**
     * @var array<string, AnalyzerInterface>
     */
    protected array $analyzers = [];

    /**
     * تسجيل مكون تحليل جديد في السجل المنصي (Open for Extension).
     */
    public function register(AnalyzerInterface $analyzer): void
    {
        $key = $analyzer->getKey();
        if (empty($key)) {
            throw new InvalidArgumentException("Analyzer key cannot be empty.");
        }

        $this->analyzers[$key] = $analyzer;
    }

    public function get(string $key): ?AnalyzerInterface
    {
        return $this->analyzers[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->analyzers[$key]);
    }

    /**
     * @return array<string, AnalyzerInterface>
     */
    public function all(): array
    {
        return $this->analyzers;
    }
}

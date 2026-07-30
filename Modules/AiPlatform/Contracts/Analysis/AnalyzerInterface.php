<?php
// واجهة المكون المستقل للمحلل الذكي المنصي القابل للإضافة والتوسع وتحديد سياسات التنفيذ (Analyzer Plugin Architecture).

namespace Modules\AiPlatform\Contracts\Analysis;

use Modules\AiPlatform\Contracts\Engines\ExecutionEngineInterface;
use Modules\AiPlatform\DTOs\AnalysisRequestDTO;
use Modules\AiPlatform\Enums\ExecutionPolicy;

interface AnalyzerInterface
{
    /**
     * المعرف الفريد لمحلل المنصة (مثل: financial_sms, invoice_ocr, bank_statement).
     */
    public function getKey(): string;

    /**
     * إصدار المكون المستقل للمحلل (مثل: 1.0.0).
     */
    public function getVersion(): string;

    /**
     * سياسة التنفيذ المعتمدة للمحلل (مثل: ExecutionPolicy::SYSTEM_ONLY لتنفيذ المحلل بحسابات النظام فقط).
     */
    public function getExecutionPolicy(): ExecutionPolicy;

    /**
     * قائمة أنواع البيانات التي يعلن المحلل عن قدرته على معالجتها (Self-Declaring Capabilities).
     */
    public function getSupportedTypes(): array;

    /**
     * قائمة المزودين والمصادر المدعومة من المحلل.
     */
    public function getSupportedProviders(): array;

    /**
     * تنفيذ منطق التحليل وتجهيز البرومبت والتدقيق باستخدام محرك التنفيذ الممرر.
     */
    public function analyze(AnalysisRequestDTO $request, ExecutionEngineInterface $executionEngine): array;
}

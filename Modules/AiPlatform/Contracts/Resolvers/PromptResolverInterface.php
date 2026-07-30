<?php
// واجهة استخراج وتجهيز البرومبت واصداراته لمختلف أنواع التحليل المنصي.

namespace Modules\AiPlatform\Contracts\Resolvers;

interface PromptResolverInterface
{
    /**
     * إرجاع البرومبت واصدار المخطط واصدار البرومبت بناء على نوع التحليل والمزود.
     */
    public function resolve(string $content, string $providerKey = 'general'): array;
}

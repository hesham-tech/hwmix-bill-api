<?php

namespace Modules\AiPlatform\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AiPlatform\Facades\AI;

/**
 * يختبر هذا الكلاس التنفيذ المباشر للقدرات
 */
class DirectCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = \Modules\AiPlatform\Models\AiProvider::create([
            'key' => 'gemini',
            'label' => 'Google Gemini',
            'type' => 'llm',
            'driver_class' => 'Modules\AiPlatform\Drivers\GeminiDriver',
            'is_active' => true,
        ]);

        $model = \Modules\AiPlatform\Models\AiModel::create([
            'ai_provider_id' => $provider->id,
            'model_id' => 'gemini-2.5-flash',
            'label' => 'Gemini 2.5 Flash',
            'input_price_per_1k' => 0.00015,
            'output_price_per_1k' => 0.0006,
            'is_active' => true,
        ]);

        \Modules\AiPlatform\Models\AiModelCapability::create([
            'ai_model_id' => $model->id,
            'ai_capability_key' => 'text.generate',
        ]);

        \Modules\AiPlatform\Models\AiProviderAccount::create([
            'company_id' => 1,
            'ai_provider_id' => $provider->id,
            'label' => 'Gemini Account',
            'api_key_encrypted' => 'test-key',
            'api_key_hint' => '...test',
            'priority' => 1,
            'is_active' => true,
            'health_status' => 'healthy',
        ]);
    }

    public function test_can_execute_direct_capability()
    {
        $result = AI::capability('text.generate')
            ->prompt('product.description.generate')
            ->with(['product_name' => 'Test', 'features' => 'Fast'])
            ->forCompany(1)
            ->run();

        $this->assertInstanceOf(\Modules\AiPlatform\DTOs\ExecutionResultDTO::class, $result);
    }
}

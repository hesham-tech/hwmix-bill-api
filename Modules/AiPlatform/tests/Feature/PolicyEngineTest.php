<?php

namespace Modules\AiPlatform\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * يختبر هذا الكلاس محرك السياسات
 */
class PolicyEngineTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        \Modules\AiPlatform\Models\AiPolicy::create([
            'company_id' => 1,
            'name' => 'Default Policy',
            'label' => 'Default Policy',
            'type' => 'allow',
            'action' => 'allow',
            'conditions' => json_encode([]),
            'is_active' => true,
            'priority' => 1,
        ]);
    }

    public function test_can_evaluate_policy()
    {
        $request = new \Modules\AiPlatform\DTOs\ExecutionRequestDTO(
            capabilityKey: 'text.generate',
            sourceType: 'direct',
            companyId: 1
        );
        $policyEngine = app(\Modules\AiPlatform\Contracts\Engines\PolicyEngineInterface::class);
        $decision = $policyEngine->evaluate($request, 1, ['admin']);
        $this->assertInstanceOf(\Modules\AiPlatform\DTOs\PolicyDecisionDTO::class, $decision);
    }
}

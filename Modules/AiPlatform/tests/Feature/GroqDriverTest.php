<?php

namespace Modules\AiPlatform\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\AiPlatform\Drivers\GroqDriver;
use Modules\AiPlatform\Enums\Capability;
use Modules\AiPlatform\DTOs\ExecutionResultDTO;

/**
 * اختبارات تشغيل درايفر Groq AI وموديل Llama 3.1
 */
class GroqDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_groq_driver_capabilities_and_supports()
    {
        $driver = new GroqDriver();

        $this->assertEquals('groq', $driver->getName());
        $this->assertTrue($driver->supports(Capability::TextGenerate));
        $this->assertContains('text.generate', $driver->capabilities());
    }

    public function test_groq_driver_execute_success()
    {
        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'وصف منتج تجريبي رائع من Llama 3.1'
                        ]
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 20,
                    'completion_tokens' => 40,
                    'total_tokens' => 60
                ]
            ], 200)
        ]);

        $driver = new GroqDriver();
        $result = $driver->execute(
            'اكتب وصفاً تجريبياً',
            ['model_id' => 'llama-3.1-8b-instant'],
            'gsk_test_key'
        );

        $this->assertInstanceOf(ExecutionResultDTO::class, $result);
        $this->assertTrue($result->successful);
        $this->assertEquals('وصف منتج تجريبي رائع من Llama 3.1', $result->content);
        $this->assertEquals(20, $result->inputTokens);
        $this->assertEquals(40, $result->outputTokens);
    }
}

<?php

namespace Tests\Unit;

use App\Support\Money;
use Modules\Payment\Services\WebhookSignature;
use PHPUnit\Framework\TestCase;

class StoreMoneyAndSignatureTest extends TestCase
{
    public function test_money_retains_decimal_precision_and_signed_balances(): void
    {
        $this->assertSame(10001, Money::minor('100.01'));
        $this->assertSame(-10001, Money::minor('-100.01'));
        $this->assertSame('-100.01', Money::decimal(-10001));
        $this->assertSame('0.05', Money::decimal(5));
        $this->assertSame(0, Money::minor('0.0000'));
    }

    public function test_money_rejects_silent_rounding(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('1.005');
    }

    public function test_money_rejects_overflow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::minor('99999999999999999999.00');
    }

    public function test_stripe_signature_binds_raw_payload_and_timestamp(): void
    {
        $body = '{"id":"evt_test"}';
        $signature = hash_hmac('sha256', '1000.' . $body, 'test-secret');
        $this->assertTrue(WebhookSignature::stripe($body, "t=1000,v1={$signature}", 'test-secret', 1100));
        $this->assertFalse(WebhookSignature::stripe($body . ' ', "t=1000,v1={$signature}", 'test-secret', 1100));
        $this->assertFalse(WebhookSignature::stripe($body, "t=1000,v1={$signature}", 'test-secret', 1500));
        $this->assertFalse(WebhookSignature::stripe($body, '', 'test-secret', 1100));
    }

    public function test_paymob_rejects_unsigned_or_incomplete_success_claims(): void
    {
        $this->assertFalse(WebhookSignature::paymob(['success' => true], str_repeat('a', 128), 'test-secret'));
        $this->assertFalse(WebhookSignature::paymob(['success' => true], '', 'test-secret'));
    }
}

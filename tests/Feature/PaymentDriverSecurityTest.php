<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Modules\Payment\Services\Gateways\PaymobGateway;
use Modules\Payment\Services\Gateways\StripeGateway;
use Modules\Payment\Models\PaymentGateway;
use Modules\Payment\Http\Resources\PaymentGatewayResource;
use Tests\TestCase;

class PaymentDriverSecurityTest extends TestCase
{
    public function test_unsigned_success_flag_cannot_override_unpaid_paymob_order(): void {
        Http::preventStrayRequests();
        Http::fake([
            '*auth/tokens'=>Http::response(['token'=>'test']),
            '*ecommerce/orders/123'=>Http::response(['id'=>123,'amount_cents'=>10000,'currency'=>'EGP','is_paid'=>false]),
        ]);
        $gateway=new PaymobGateway(['api_key'=>'test']);
        $result=$gateway->verify(['order'=>'123','success'=>true,'expected_reference'=>'123',
            'expected_amount_minor'=>10000,'expected_currency'=>'EGP']);
        $this->assertTrue($result['success']);
        $this->assertSame('pending',$result['status']);
    }
    public function test_paymob_rejects_different_amount_or_currency(): void {
        Http::preventStrayRequests();
        Http::fake(['*auth/tokens'=>Http::response(['token'=>'test']),
            '*orders/123'=>Http::response(['id'=>123,'amount_cents'=>9000,'currency'=>'USD','is_paid'=>true])]);
        $result=(new PaymobGateway(['api_key'=>'test']))->verify(['order'=>'123','expected_reference'=>'123',
            'expected_amount_minor'=>10000,'expected_currency'=>'EGP']);
        $this->assertFalse($result['success']);
    }
    public function test_stripe_requires_owner_amount_currency_and_environment_match(): void {
        Http::preventStrayRequests();
        $session=['id'=>'cs_test_123','client_reference_id'=>'9','amount_total'=>10000,
            'currency'=>'egp','livemode'=>false,'payment_status'=>'paid','payment_intent'=>'pi_test_123'];
        Http::fake(['*sessions/cs_test_123'=>Http::response($session)]);
        $gateway=new StripeGateway(['secret_key'=>'test','_test_mode'=>true]);
        $data=['session_id'=>'cs_test_123','expected_reference'=>'cs_test_123','expected_transaction_id'=>'9',
            'expected_amount_minor'=>10000,'expected_currency'=>'EGP'];
        $this->assertSame('completed',$gateway->verify($data)['status']);
        $this->assertFalse($gateway->verify(array_replace($data,['expected_transaction_id'=>'10']))['success']);
        $this->assertFalse($gateway->verify(array_replace($data,['expected_amount_minor'=>9999]))['success']);
        $this->assertFalse($gateway->verify(array_replace($data,['expected_currency'=>'USD']))['success']);
        $this->assertFalse((new StripeGateway(['secret_key'=>'test','_test_mode'=>false]))->verify($data)['success']);
    }
    public function test_gateway_response_never_returns_decrypted_secrets(): void {
        $gateway=new PaymentGateway();
        $gateway->forceFill(['id'=>1,'driver'=>'paymob','name'=>'Test','config'=>[
            'api_key'=>Crypt::encryptString('secret-api'),'hmac_secret'=>Crypt::encryptString('secret-hmac'),
            'integration_id'=>Crypt::encryptString('123'),'iframe_id'=>Crypt::encryptString('456')]]);
        $data=(new PaymentGatewayResource($gateway))->resolve();
        $json=json_encode($data);
        $this->assertStringNotContainsString('secret-api',$json);
        $this->assertStringNotContainsString('secret-hmac',$json);
        $this->assertSame(['api_key'=>true,'hmac_secret'=>true],$data['credentials_configured']);
        $this->assertSame('123',$data['config']['integration_id']);
    }
}

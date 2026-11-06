<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\Http;
use Modules\Payment\Services\Gateways\KashierGateway;
use Modules\Payment\Services\WebhookSignature;
use Modules\Payment\Models\PaymentTransaction;
use Tests\TestCase;
class KashierDriverTest extends TestCase {
    private function gateway(): KashierGateway { return new KashierGateway(['secret_key'=>'test-secret','api_key'=>'test-api','merchant_id'=>'MID-TEST','_test_mode'=>true]); }
    private function fixtureSession(): array { return ['sessionId'=>'session_test_123','merchantId'=>'MID-TEST','merchantOrderId'=>'txn-9','amount'=>'100.00','currency'=>'EGP','status'=>'SUCCESS']; }
    private function order(): array { return ['sessionId'=>'session_test_123','merchantId'=>'MID-TEST','merchantOrderId'=>'txn-9','orderId'=>'order-test-123',
        'merchantDetails'=>['isLive'=>false],'order'=>['amount'=>'100.00','currency'=>'EGP'],'transactions'=>[
            ['transactionId'=>'TX-123','operation'=>'authorize','status'=>'SUCCESS','amount'=>'100.00','currency'=>'EGP'],
            ['transactionId'=>'TX-456','operation'=>'pay','status'=>'SUCCESS','amount'=>'100.00','currency'=>'EGP'],
        ]]; }
    private function verifyData(): array { return ['session_id'=>'session_test_123','expected_reference'=>'session_test_123','expected_transaction_id'=>'9','expected_amount_minor'=>10000,'expected_currency'=>'EGP']; }
    public function test_signed_amount_and_identity_fields_cannot_be_omitted_or_changed(): void {
        $data=['amount'=>1,'channel'=>'online | e-commerce','currency'=>'EGP','kashierOrderId'=>'9ad06b17-755b-4e21-9774-aff3e2726ac9','merchantOrderId'=>'1653481557813','method'=>'card','orderReference'=>'TEST-ORD-38855','status'=>'SUCCESS','transactionId'=>'TX-249893963','transactionResponseCode'=>'00'];
        $data['signatureKeys']=array_keys($data);
        $signature='9610477b2255b2a8ef84fd89adfaa5f1305ff9c20324205851890f1ea03109f4';
        $this->assertTrue(WebhookSignature::kashier($data,$signature,'11111'));
        $this->assertFalse(WebhookSignature::kashier(array_replace($data,['amount'=>2]),$signature,'11111'));
        $this->assertFalse(WebhookSignature::kashier(array_replace($data,['signatureKeys'=>[]]),hash_hmac('sha256','','11111'),'11111'));
    }
    public function test_authorization_is_not_capture_and_amount_mode_and_account_are_verified(): void {
        Http::preventStrayRequests();$session=$this->fixtureSession();$order=$this->order();
        Http::fake(function($request)use(&$session,&$order){return Http::response(['data'=>str_contains($request->url(),'/v3/payment/orders')?[$order]:$session]);});
        $gateway=$this->gateway();$data=$this->verifyData();
        $result=$gateway->verify($data);$this->assertSame('completed',$result['status']);$this->assertSame('TX-456',$result['payment_reference']);
        $this->assertFalse($gateway->verify(array_replace($data,['expected_amount_minor'=>9999]))['success']);
        $this->assertSame('pending',$gateway->verify($data+['capture_reference'=>'TX-123'])['status']);
        $order['merchantDetails']['isLive']=true;
        $this->assertSame('pending',$gateway->verify($data)['status']);
    }
    public function test_session_creation_uses_server_reference_and_raw_secret_header(): void {
        Http::preventStrayRequests();Http::fake(['*/v3/payment/sessions'=>Http::response(['_id'=>'session_test_123','sessionUrl'=>'https://payments.kashier.io/session/session_test_123?mode=test'])]);
        $transaction=new PaymentTransaction();$transaction->forceFill(['id'=>9,'amount'=>'100.00','currency'=>'EGP']);
        $result=$this->gateway()->purchase($transaction,['success_url'=>'https://shop.example/store/payment-return']);
        $this->assertTrue($result['success']);
        Http::assertSent(fn($r)=>$r->hasHeader('Authorization','test-secret')&&$r['amount']==='100.00'&&$r['order']==='txn-9'&&$r['manualCapture']===false);
    }
}

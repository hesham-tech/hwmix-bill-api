<?php
namespace Tests\Feature;
use App\Models\{Company,CompanySubscription,Plan,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Inventory\Models\{Product,ProductVariant,Stock,Warehouse,Unit};
use Modules\Legal\Models\{LegalDocument,LegalDocumentVersion};
use Modules\Store\Models\{StoreSellerProfile,StoreShippingAccount,StoreShippingRate,StoreOrder};
use Modules\Store\Services\StoreSettingsService;
use Tests\TestCase;

class StoreCheckoutTest extends TestCase
{
    use RefreshDatabase;
    private User $customer;
    private Stock $stock;
    private Product $product;
    private ProductVariant $variant;
    private array $payload;
    protected function setUp(): void {
        parent::setUp();
        $platform=Company::factory()->create();
        config(['app.master_company_id'=>$platform->id]);
        $seller=Company::factory()->create();
        $this->customer=User::factory()->create(['company_id'=>$platform->id,'active_company_id'=>$platform->id,'email_verified_at'=>now()]);
        $plan=Plan::create(['name'=>'Marketplace','code'=>'marketplace-fixture','price'=>0,'currency'=>'EGP',
            'duration'=>1,'duration_unit'=>'months','is_active'=>true,'features'=>[
                'flags'=>['store_publish'=>true],'store_policy'=>['funding_mode'=>'credit','credit_limit_minor'=>100000,'commission_percent'=>'10.00']]]);
        CompanySubscription::create(['company_id'=>$seller->id,'plan_id'=>$plan->id,'status'=>'active','starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth()]);
        $warehouse=Warehouse::factory()->create(['company_id'=>$seller->id]);
        $group=\Modules\Inventory\Models\UnitGroup::create(['name'=>'Count','type'=>'count','company_id'=>$seller->id]);
        $unit=Unit::create(['unit_group_id'=>$group->id,'name'=>'Piece','code'=>'piece-test','decimal_places'=>0,'is_active'=>true,'company_id'=>$seller->id]);
        $this->product=Product::factory()->create(['company_id'=>$seller->id,'is_active_in_store'=>true,'base_unit_id'=>$unit->id]);
        $this->variant=ProductVariant::factory()->create(['company_id'=>$seller->id,'product_id'=>$this->product->id,'base_unit_id'=>$unit->id,'retail_price'=>'100.00']);
        $this->stock=Stock::factory()->create(['company_id'=>$seller->id,'variant_id'=>$this->variant->id,'warehouse_id'=>$warehouse->id,'quantity'=>10,'reserved'=>0,'cost'=>'25.00']);
        $account=StoreShippingAccount::create(['company_id'=>$seller->id,'name'=>'Manual courier','driver'=>'manual','is_active'=>true,'cod_beneficiary'=>'seller']);
        StoreShippingRate::create(['shipping_account_id'=>$account->id,'governorate_code'=>'cairo','fee_minor'=>5000]);
        StoreSellerProfile::create(['company_id'=>$seller->id,'warehouse_id'=>$warehouse->id,'shipping_account_id'=>$account->id,
            'is_enabled'=>true,'pickup_address'=>['recipient_name'=>'Seller','phone'=>'01012345678','city'=>'Cairo','governorate_code'=>'cairo','street'=>'Seller pickup']]);
        $documents=[];
        foreach(['terms-of-use','privacy-policy','refund-policy'] as $key) {
            $document=LegalDocument::create(['key'=>$key,'is_active'=>true]);
            LegalDocumentVersion::create(['legal_document_id'=>$document->id,'version'=>'1.0','title'=>$key,'content'=>'Published test policy',
                'status'=>'published','published_at'=>now()->subHour()]);
            $documents[]=$document->id;
        }
        app(StoreSettingsService::class)->save(['enabled'=>true,'legal_setup_confirmed'=>true,'tax_mode'=>'none',
            'legal_document_ids'=>$documents,'return_policy'=>['window_days'=>14]]);
        $this->payload=['checkout_key'=>(string)Str::uuid(),'payment_method'=>'cod','notes'=>'Test delivery',
            'guest_address'=>['recipient_name'=>'Buyer','phone'=>'01012345678','governorate_code'=>'cairo','city'=>'Nasr City','street'=>'Buyer address'],
            'items'=>[['product_id'=>$this->product->id,'variant_id'=>$this->variant->id,'quantity'=>2]]];
    }
    private function submit(array $payload): array {
        $quote=$this->postJson('/api/v1/store/checkout/quote',$payload)->assertOk()->json('data');
        return array_merge($payload,['quote_id'=>$quote['id'],'legal_accepted'=>true,
            'legal_version_ids'=>array_column($quote['legal_documents'],'version_id')]);
    }
    private function deliveredOrder(bool $collected=true): StoreOrder {
        $this->actingAs($this->customer,'sanctum');
        $result=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $order=StoreOrder::findOrFail($result['id']); $sub=$order->subOrders->first();
        $service=app(\Modules\Store\Services\StoreOrderService::class);
        $service->confirm($order,true);$service->sellerReady($sub);
        $shipment=\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->firstOrFail();
        app(\Modules\Store\Services\StoreShipmentService::class)->dispatchManual($shipment,'RETURN-TEST');
        $service->updateSubOrderStatus($sub,'delivered',['collected'=>$collected]);
        return $order->fresh();
    }
    public function test_partial_cod_return_restores_only_received_stock_and_reverses_only_its_commission(): void {
        $this->stock->update(['cost'=>'25.00']);
        $order=$this->deliveredOrder();$sub=$order->subOrders->first();$item=$order->items->first();
        \Modules\Sales\Models\InvoiceType::create(['name'=>'Store return fixture','code'=>'sale','context'=>'sales','company_id'=>$sub->company_id]);
        app(\Modules\Store\Services\StoreInvoiceBridge::class)->create($sub->id);
        $data=['item_id'=>$item->id,'operation_key'=>(string)Str::uuid(),'quantity'=>1,'reason'=>'defect'];
        $first=$this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertCreated()->json('data');
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertCreated()->assertJsonPath('data.id',$first['id']);
        $this->assertSame('8.000000',$this->stock->refresh()->quantity);
        $service=app(\Modules\Store\Services\StoreReturnService::class);$return=\Modules\Store\Models\StoreReturn::findOrFail($first['id']);
        $service->act($return,['action'=>'approve']);
        $received=$service->act($return,['action'=>'receive','condition'=>'saleable']);
        $service->act($return,['action'=>'receive','condition'=>'saleable']);
        $this->assertSame('9.000000',$this->stock->refresh()->quantity);
        $this->assertSame(12500,$received->refund_minor);
        $this->assertSame(-1000,\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail()->balance_minor);
        $this->assertSame('completed',$service->accounting($return->id));
        $this->assertSame('completed',$service->accounting($return->id));
        $refund=\Modules\Store\Models\StoreRefund::findOrFail($received->store_refund_id);
        app(\Modules\Store\Services\StoreRefundService::class)->confirmManual($refund,'SELLER-REFUND-001','seller');
        app(\Modules\Store\Services\StoreRefundService::class)->confirmManual($refund,'SELLER-REFUND-001','seller');
        $this->assertSame('completed',$refund->fresh()->status);
        $this->assertSame('partial',$order->fresh()->payment_status);
        $this->assertSame(-1000,\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail()->balance_minor);
        $this->assertEquals(\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','debit')->sum('amount'),\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','credit')->sum('amount'));
    }
    public function test_return_rejects_another_customer_and_excess_quantity(): void {
        $order=$this->deliveredOrder();$item=$order->items->first();
        $data=['item_id'=>$item->id,'operation_key'=>(string)Str::uuid(),'quantity'=>3,'reason'=>'defect'];
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertStatus(422);
        $other=User::factory()->create();$this->actingAs($other,'sanctum');$data['quantity']=1;
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertNotFound();
        $this->assertDatabaseCount('store_returns',0);
    }
    public function test_return_refunds_tax_once_and_not_twice(): void {
        app(StoreSettingsService::class)->save(['tax_mode'=>'exclusive','default_tax_rate_bps'=>1400]);
        $order=$this->deliveredOrder();$item=$order->items->first();
        $this->assertSame('278.00',$order->total_amount);
        $service=app(\Modules\Store\Services\StoreReturnService::class);
        $return=$service->request($order,['item_id'=>$item->id,'operation_key'=>(string)Str::uuid(),'quantity'=>1,'reason'=>'defect']);
        $this->assertSame(11400,$return->refund_minor);
        $service->act($return,['action'=>'approve']);
        $return=$service->act($return,['action'=>'receive','condition'=>'damaged']);
        $this->assertSame(13900,$return->refund_minor);
        $this->assertSame(1400,$return->inspection_snapshot['tax_minor']);
        $this->assertSame('8.000000',$this->stock->refresh()->quantity);
    }
    public function test_account_is_required_for_actual_checkout_by_default(): void {
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertStatus(422)->assertJsonValidationErrors('account');
        $this->assertDatabaseCount('store_orders',0);
    }
    public function test_creating_order_twice_uses_one_reservation_and_customer_identity(): void {
        $this->actingAs($this->customer,'sanctum');
        $payload=$this->submit($this->payload);
        $first=$this->postJson('/api/v1/store/orders',$payload)->assertCreated();
        $second=$this->postJson('/api/v1/store/orders',$payload)->assertCreated();
        $this->assertSame($first->json('data.id'),$second->json('data.id'));
        $this->assertSame($this->customer->id,$first->json('data.user_id'));
        $this->assertSame('250.00',$first->json('data.total_amount'));
        $this->assertSame('2.000000',$this->stock->refresh()->reserved);
        $this->assertDatabaseCount('store_orders',1);
        $this->assertDatabaseCount('store_account_holds',1);
    }
    public function test_cancellation_releases_only_its_own_allocation(): void {
        $this->actingAs($this->customer,'sanctum');
        $first=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $another=array_replace($this->payload,['checkout_key'=>(string)Str::uuid()]);
        $this->postJson('/api/v1/store/orders',$this->submit($another))->assertCreated();
        $this->postJson('/api/v1/store/orders/'.$first['id'].'/cancel')->assertOk();
        $this->assertSame('2.000000',$this->stock->refresh()->reserved);
        $this->assertDatabaseCount('store_stock_allocations',2);
    }
    public function test_another_account_cannot_read_or_cancel_an_order(): void {
        $this->actingAs($this->customer,'sanctum');
        $order=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $other=User::factory()->create(['company_id'=>$this->customer->company_id,'active_company_id'=>$this->customer->company_id,'email_verified_at'=>now()]);
        $this->actingAs($other,'sanctum');
        $this->getJson('/api/v1/store/orders/'.$order['order_number'])->assertNotFound();
        $token=app(\Modules\Store\Services\StoreOrderService::class)->trackingToken(StoreOrder::findOrFail($order['id']));
        $this->withHeader('X-Order-Token',$token)->getJson('/api/v1/store/orders/'.$order['order_number'])->assertNotFound();
        $this->postJson('/api/v1/store/orders/'.$order['id'].'/cancel')->assertNotFound();
        $this->assertSame('2.000000',$this->stock->refresh()->reserved);
    }
    public function test_stock_shortage_rolls_back_order_and_financial_holds(): void {
        $this->actingAs($this->customer,'sanctum');
        $payload=$this->submit($this->payload);
        $this->stock->update(['quantity'=>1]);
        $this->postJson('/api/v1/store/orders',$payload)->assertStatus(422);
        $this->assertDatabaseCount('store_orders',0);
        $this->assertDatabaseCount('store_account_holds',0);
        $this->assertDatabaseCount('store_stock_allocations',0);
    }
    public function test_changed_price_requires_a_new_quote_without_charging(): void {
        $this->actingAs($this->customer,'sanctum');
        $payload=$this->submit($this->payload);
        $this->variant->update(['retail_price'=>'120.00']);
        $this->postJson('/api/v1/store/orders',$payload)->assertStatus(422);
        $this->assertDatabaseCount('store_orders',0);
    }
    public function test_cod_delivery_posts_one_invoice_without_cashbox_or_customer_debt_movements(): void {
        $this->stock->update(['cost'=>'25.00']);
        $this->actingAs($this->customer,'sanctum');
        $orderData=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $order=StoreOrder::findOrFail($orderData['id']);
        $sub=$order->subOrders->first();
        \Modules\Sales\Models\InvoiceType::create(['name'=>'Store sale','code'=>'sale','context'=>'sales','company_id'=>$sub->company_id]);
        $transactions=\Illuminate\Support\Facades\DB::table('transactions')->count();
        $orders=app(\Modules\Store\Services\StoreOrderService::class);
        $orders->confirm($order,true);
        $orders->sellerReady($sub);
        $shipment=\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->firstOrFail();
        app(\Modules\Store\Services\StoreShipmentService::class)->dispatchManual($shipment,'TRACK-001');
        $orders->updateSubOrderStatus($sub,'delivered',['collected'=>true]);
        $bridge=app(\Modules\Store\Services\StoreInvoiceBridge::class);
        $this->assertSame('completed',$bridge->create($sub->id));
        $this->assertSame('completed',$bridge->create($sub->id));
        $this->assertDatabaseCount('invoices',1);
        $invoice=\Modules\Sales\Models\Invoice::withoutGlobalScopes()->firstOrFail();
        $this->assertTrue((bool)$invoice->is_marketplace_managed);
        $this->assertNull($invoice->cash_box_id);
        $this->assertSame('paid',$invoice->payment_status);
        $invoice->load('customer');
        $presentation=(new \App\Http\Resources\Invoice\InvoiceResource($invoice))->resolve(request());
        $this->assertArrayNotHasKey('balance',$presentation['customer']);
        $this->assertArrayNotHasKey('roles',$presentation['customer']);
        $this->assertNull($presentation['user_balance']);
        $this->assertSame($transactions,\Illuminate\Support\Facades\DB::table('transactions')->count());
        $this->assertSame('8.000000',$this->stock->refresh()->quantity);
        $this->assertSame('0.000000',$this->stock->reserved);
        $this->assertSame(-2000,\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail()->balance_minor);
        $debit=\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','debit')->sum('amount');
        $credit=\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','credit')->sum('amount');
        $this->assertEquals($debit,$credit);
    }

    public static function rtoConditions(): array {return [['saleable','10.000000'],['damaged','8.000000']];}
    #[\PHPUnit\Framework\Attributes\DataProvider('rtoConditions')]
    public function test_uncollected_cod_rto_restores_only_inspected_stock_without_refund_or_sale_credit(string $condition,string $quantity): void {
        app(StoreSettingsService::class)->save(['seller_policy'=>['rto_fee_payer'=>'seller']]);$this->stock->update(['cost'=>'25.00']);
        $this->actingAs($this->customer,'sanctum');
        $created=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $order=StoreOrder::findOrFail($created['id']);$sub=$order->subOrders->first();$orders=app(\Modules\Store\Services\StoreOrderService::class);
        $orders->confirm($order,true);$orders->sellerReady($sub);
        $shipment=\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->firstOrFail();
        app(\Modules\Store\Services\StoreShipmentService::class)->dispatchManual($shipment,'RTO-FIXTURE');
        $this->assertSame('2.000000',$this->stock->refresh()->reserved);
        $data=['confirmed'=>true,'condition'=>$condition,'reason'=>'customer_refused','carrier_reference'=>'RTO-RECEIVED-001','carrier_cost'=>'25.00'];
        $rto=app(\Modules\Store\Services\StoreRtoService::class);
        $receipt=$rto->receive($sub,$data);$this->assertSame($receipt->id,$rto->receive($sub,$data)->id);
        $this->assertSame('cancelled',$sub->refresh()->status);$this->assertSame('returned',$shipment->refresh()->status);
        $this->assertSame($quantity,$this->stock->refresh()->quantity);$this->assertSame('0.000000',$this->stock->reserved);
        $this->assertDatabaseCount('store_refunds',0);$this->assertDatabaseCount('invoices',0);$this->assertDatabaseCount('store_return_to_origins',1);
        $account=\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail();
        $this->assertSame(0,$account->balance_minor);$this->assertSame(0,$account->pending_minor);
        $this->assertSame(0,\Modules\Store\Models\StoreAccountHold::where('status','active')->count());
        $this->assertEquals(\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','debit')->sum('amount'),\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','credit')->sum('amount'));
    }

    public function test_online_attempt_resumes_after_gateway_disable_and_rto_refunds_with_the_original_account(): void {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*'=>function($request){
            if(str_ends_with($request->url(),'/checkout/sessions')&&$request->method()==='POST')return \Illuminate\Support\Facades\Http::response(['id'=>'cs_fixture','url'=>'https://checkout.stripe.com/c/pay/cs_fixture']);
            if(str_ends_with($request->url(),'/checkout/sessions/cs_fixture')) {
                $transaction=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->firstOrFail();
                return \Illuminate\Support\Facades\Http::response(['id'=>'cs_fixture','client_reference_id'=>(string)$transaction->id,'amount_total'=>25000,'currency'=>'egp','livemode'=>false,'payment_status'=>'paid','payment_intent'=>'pi_fixture']);
            }
            if(str_ends_with($request->url(),'/refunds'))return \Illuminate\Support\Facades\Http::response(['id'=>'re_fixture','status'=>'succeeded','amount'=>25000,'currency'=>'egp','payment_intent'=>'pi_fixture']);
            return \Illuminate\Support\Facades\Http::response([],500);
        }]);
        app(StoreSettingsService::class)->save(['online_enabled'=>true]);
        $gateway=\Modules\Payment\Models\PaymentGateway::create(['company_id'=>config('app.master_company_id'),'driver'=>'stripe','name'=>'Fixture stripe',
            'is_active'=>true,'is_test_mode'=>true,'config'=>['secret_key'=>'sk_test_fixture','webhook_secret'=>'whsec_fixture']]);
        $this->actingAs($this->customer,'sanctum');$payload=$this->payload;$payload['payment_method']='online';$payload['payment_gateway_id']=$gateway->id;
        $created=$this->postJson('/api/v1/store/orders',$this->submit($payload))->assertCreated()->json('data');
        $order=StoreOrder::findOrFail($created['id']);
        $first=$this->postJson('/api/v1/store/orders/'.$order->order_number.'/payment',['payment_gateway_id'=>$gateway->id])->assertOk()->json('data');
        $gateway->update(['is_active'=>false]);
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/payment',[])->assertOk()->assertJsonPath('data.transaction_id',$first['transaction_id'])->assertJsonPath('data.payment_url',$first['payment_url']);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $transaction=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->findOrFail($first['transaction_id']);
        $result=app(\Modules\Payment\Services\PaymentVerificationService::class)->verify($transaction);
        $this->assertTrue($result['success']);$this->assertSame('paid',$order->refresh()->payment_status);
        $gateway->delete();
        $sub=$order->subOrders->first();$orders=app(\Modules\Store\Services\StoreOrderService::class);$orders->confirm($order,true);$orders->sellerReady($sub);
        $shipment=\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->firstOrFail();
        app(\Modules\Store\Services\StoreShipmentService::class)->dispatchManual($shipment,'PAID-RTO-FIXTURE');
        app(\Modules\Store\Services\StoreRtoService::class)->receive($sub,['confirmed'=>true,'condition'=>'saleable','reason'=>'courier_failed','carrier_reference'=>'PAID-RTO-RECEIPT','carrier_cost'=>'25.00']);
        $refund=\Modules\Store\Models\StoreRefund::firstOrFail();$this->assertSame(25000,$refund->amount_minor);
        $refunds=app(\Modules\Store\Services\StoreRefundService::class);
        $this->assertSame('completed',$refunds->process($refund));$this->assertSame('completed',$refunds->process($refund));
        $this->assertSame('refunded',$order->refresh()->payment_status);$this->assertSame('refunded',$transaction->refresh()->status);
        $this->assertSame(25000,$transaction->refunded_minor);$this->assertSame('10.000000',$this->stock->refresh()->quantity);$this->assertSame('0.000000',$this->stock->reserved);
        $this->assertDatabaseCount('store_refunds',1);$this->assertDatabaseCount('invoices',0);\Illuminate\Support\Facades\Http::assertSentCount(3);
    }

    public static function refundFundingModes(): array { return ['gateway refund'=>[false],'manual bank refund'=>[true]]; }
    #[\PHPUnit\Framework\Attributes\DataProvider('refundFundingModes')]
    public function test_gateway_bank_settlement_and_later_refund_are_reconciled_without_double_booking(bool $manual): void {
        app(StoreSettingsService::class)->save(['online_enabled'=>true,'seller_policy'=>['commission_timing'=>'remitted']]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*'=>function($request){
            if(str_ends_with($request->url(),'/checkout/sessions')&&$request->method()==='POST')return \Illuminate\Support\Facades\Http::response(['id'=>'cs_settlement','url'=>'https://checkout.stripe.com/c/pay/cs_settlement']);
            if(str_ends_with($request->url(),'/checkout/sessions/cs_settlement')){
                $p=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->firstOrFail();
                return \Illuminate\Support\Facades\Http::response(['id'=>'cs_settlement','client_reference_id'=>(string)$p->id,'amount_total'=>25000,'currency'=>'egp','livemode'=>false,'payment_status'=>'paid','payment_intent'=>'pi_settlement']);
            }
            if(str_ends_with($request->url(),'/refunds'))return \Illuminate\Support\Facades\Http::response(['id'=>'re_settlement','status'=>'succeeded','amount'=>(int)$request['amount'],'currency'=>'egp','payment_intent'=>'pi_settlement']);
            return \Illuminate\Support\Facades\Http::response([],500);
        }]);
        $gateway=\Modules\Payment\Models\PaymentGateway::create(['company_id'=>config('app.master_company_id'),'driver'=>'stripe','name'=>'Fixture','is_active'=>true,'is_test_mode'=>true,'config'=>['secret_key'=>'sk_test_fixture','webhook_secret'=>'whsec_fixture']]);
        $this->actingAs($this->customer,'sanctum');$payload=$this->payload;$payload['payment_method']='online';$payload['payment_gateway_id']=$gateway->id;
        $created=$this->postJson('/api/v1/store/orders',$this->submit($payload))->assertCreated()->json('data');$order=StoreOrder::findOrFail($created['id']);
        $started=$this->postJson('/api/v1/store/orders/'.$order->order_number.'/payment',['payment_gateway_id'=>$gateway->id])->assertOk()->json('data');
        $payment=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->findOrFail($started['transaction_id']);
        $this->assertTrue(app(\Modules\Payment\Services\PaymentVerificationService::class)->verify($payment)['success']);
        $sub=$order->subOrders->first();$orders=app(\Modules\Store\Services\StoreOrderService::class);$orders->sellerReady($sub);
        $shipment=\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$sub->id)->firstOrFail();
        app(\Modules\Store\Services\StoreShipmentService::class)->dispatchManual($shipment,'SETTLEMENT-SHIPMENT');
        $orders->updateSubOrderStatus($sub,'delivered');$this->assertNull($sub->refresh()->commission_recognized_at);
        \Modules\Sales\Models\InvoiceType::create(['name'=>'Settlement fixture','code'=>'sale','context'=>'sales','company_id'=>$sub->company_id]);
        app(\Modules\Store\Services\StoreInvoiceBridge::class)->create($sub->id);
        $service=app(\Modules\Store\Services\StoreGatewaySettlementService::class);
        $this->assertSame(25000,$service->remaining($payment->refresh()));
        $batch=['payment_ids'=>[$payment->id],'net_amount'=>'240.00','fee'=>'10.00','recoverable_tax'=>'0.00','reference'=>'BANK-PROVIDER-DEPOSIT','operation_key'=>(string)Str::uuid(),'confirmed'=>true];
        $first=$service->record($batch);$this->assertSame($first->id,$service->record($batch)->id);
        $this->assertSame(0,$service->remaining($payment));$this->assertNotNull($sub->refresh()->commission_recognized_at);
        $returnService=app(\Modules\Store\Services\StoreReturnService::class);
        $return=$returnService->request($order,['item_id'=>$order->items->first()->id,'quantity'=>1,'reason'=>'defect','operation_key'=>(string)Str::uuid()]);
        $returnService->act($return,['action'=>'approve']);$returnService->act($return,['action'=>'receive','condition'=>'damaged','confirmed'=>true]);
        $refund=\Modules\Store\Models\StoreRefund::firstOrFail();
        if($manual)app(\Modules\Store\Services\StoreRefundService::class)->confirmManual($refund,'BANK-CUSTOMER-REFUND','platform');
        else $this->assertSame('completed',app(\Modules\Store\Services\StoreRefundService::class)->process($refund));
        $this->assertSame('completed',$returnService->accounting($return->id));
        $this->assertSame($manual?0:-12500,$service->remaining($payment));
        $adjustment=['payment_ids'=>[$payment->id],'net_amount'=>'-125.00','fee'=>'0.00','reference'=>'BANK-PROVIDER-REFUND-DEBIT','operation_key'=>(string)Str::uuid(),'confirmed'=>true];
        if(!$manual)$service->record($adjustment);$this->assertSame(0,$service->remaining($payment));
        $this->assertSame(11500,\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail()->pending_minor);
        $this->assertSame(0,(int)\Illuminate\Support\Facades\DB::table('store_financial_lines')->where('account_code','asset:gateway_receivable')->sum('amount_minor'));
        $this->assertDatabaseCount('store_gateway_settlements',$manual?1:2);
    }

    public function test_public_catalog_filters_price_and_uses_sellable_units_in_the_selected_warehouse(): void {
        $other=Product::factory()->create(['company_id'=>$this->product->company_id,'is_active_in_store'=>true,'base_unit_id'=>$this->product->base_unit_id]);
        ProductVariant::factory()->create(['company_id'=>$other->company_id,'product_id'=>$other->id,'base_unit_id'=>$other->base_unit_id,'retail_price'=>'40.00','status'=>'active']);
        $this->getJson('/api/v1/store/public/products?sort=price_asc')->assertOk()->assertJsonPath('data.data.0.id',$other->id);
        $this->getJson('/api/v1/store/public/products?min_price=50&max_price=110')->assertOk()->assertJsonCount(1,'data.data')->assertJsonPath('data.data.0.id',$this->product->id);
        $group=\Modules\Inventory\Models\UnitGroup::findOrFail(Unit::findOrFail($this->product->base_unit_id)->unit_group_id);
        $pack=Unit::create(['unit_group_id'=>$group->id,'name'=>'Pack','code'=>'pack','decimal_places'=>0,'is_active'=>true,'company_id'=>$this->product->company_id]);
        \Modules\Inventory\Models\ProductVariantUnit::create(['product_variant_id'=>$this->variant->id,'unit_id'=>$pack->id,'conversion_factor_to_base'=>6]);
        $this->variant->update(['display_unit_id'=>$pack->id]);
        $result=$this->getJson('/api/v1/store/public/products/'.$this->product->id)->assertOk();
        $result->assertJsonPath('data.variants.0.available_stock',1)->assertJsonPath('data.variants.0.selling_unit_id',$pack->id);
        $this->stock->update(['quantity'=>5]);
        $this->getJson('/api/v1/store/public/products?in_stock=true')->assertOk()->assertJsonCount(0,'data.data');
        $this->stock->update(['quantity'=>12,'expiry'=>now()->subDay()]);
        $this->getJson('/api/v1/store/public/products/'.$this->product->id)->assertOk()->assertJsonPath('data.variants.0.available_stock',0);
        $this->postJson('/api/v1/store/cart/validate',['items'=>[['variant_id'=>$this->variant->id,'product_id'=>$this->product->id,'quantity'=>1]]])
            ->assertOk()->assertJsonPath('data.valid',false)->assertJsonPath('data.errors.0.available',0);

    }

    public function test_stock_with_unknown_cost_cannot_be_consumed_as_if_it_were_free(): void {
        $this->actingAs($this->customer,'sanctum');
        $created=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $sub=StoreOrder::findOrFail($created['id'])->subOrders->first();
        $this->stock->update(['cost'=>null]);
        try {
            \Illuminate\Support\Facades\DB::transaction(fn()=>app(\Modules\Store\Services\StockReservationService::class)->consumeReservation($sub));
            $this->fail('Unknown cost must require correction before dispatch.');
        } catch(\Illuminate\Validation\ValidationException $error) {$this->assertArrayHasKey('stock',$error->errors());}
        $this->assertSame('10.000000',$this->stock->refresh()->quantity);
        $this->assertDatabaseMissing('store_stock_allocations',['store_sub_order_id'=>$sub->id,'status'=>'consumed']);
    }

    public function test_seller_shipping_exception_overrides_the_platform_rate_and_cannot_silently_fall_back_when_disabled(): void {
        $account=StoreShippingAccount::firstOrFail();
        $account->update(['company_id'=>config('app.master_company_id'),'cod_beneficiary'=>'platform']);
        app(StoreSettingsService::class)->save(['platform_shipping_account_id'=>$account->id]);
        $scoped=StoreShippingRate::create(['shipping_account_id'=>$account->id,'scope_company_id'=>$this->product->company_id,'governorate_code'=>'cairo','fee_minor'=>3500,'is_active'=>true,'cod_allowed'=>true]);
        $service=app(\Modules\Store\Services\StoreShippingQuoteService::class);
        $this->assertSame(3500,$service->quote($this->product->company_id,'cairo','cod',20000)['fee_minor']);
        $this->assertSame(5000,$service->quote($this->product->company_id,'cairo','cod',20000)['carrier_fee_minor']);
        $missingRoute='تجهيز التوصيل الذاتي أو ناقل مسعّر للشركة #'.$this->product->company_id;
        $this->assertNotContains($missingRoute,app(\Modules\Store\Services\StoreReadinessService::class)->issues());
        $scoped->update(['is_active'=>false]);
        $this->assertContains($missingRoute,app(\Modules\Store\Services\StoreReadinessService::class)->issues());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->quote($this->product->company_id,'cairo','cod',20000);
    }

    public function test_changing_shipping_prices_does_not_change_an_already_accepted_order(): void {
        $this->actingAs($this->customer,'sanctum');
        $created=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $order=StoreOrder::findOrFail($created['id']);
        StoreShippingRate::query()->update(['fee_minor'=>10000]);
        $this->assertSame('50.00',$order->refresh()->shipping_total);
        $this->assertSame('50.00',$order->subOrders->first()->shipping_cost);
        $this->assertSame(5000,\Modules\Store\Models\StoreShipment::where('store_sub_order_id',$order->subOrders->first()->id)->firstOrFail()->quote_snapshot['fee_minor']);
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.shipping_minor',10000);
    }

    public function test_per_seller_nonregistered_price_and_immutable_tax_snapshot(): void {
        app(StoreSettingsService::class)->save(['tax_mode'=>'per_seller']);
        $profile=StoreSellerProfile::where('company_id',$this->product->company_id)->firstOrFail();
        $profile->update(['tax_profile'=>['status'=>'not_registered','mode'=>'none','rate_bps'=>0,'basis'=>'Seller declaration below registration threshold','product_rules'=>[]]]);
        $order=$this->deliveredOrder();
        $this->assertSame('250.00',$order->total_amount);
        $this->assertSame('0.00',$order->tax_total);
        $this->assertSame('not_registered',$order->items->first()->tax_snapshot['status']);
        $profile->update(['tax_profile'=>['status'=>'registered','mode'=>'exclusive','rate_bps'=>1400,'registration_number'=>'VAT-FIXTURE','basis'=>'Fixture verified registration']]);
        $this->assertSame('not_registered',$order->items->first()->fresh()->tax_snapshot['status']);
    }
    public function test_mixed_seller_basket_does_not_tax_nonregistered_seller(): void {
        $sellerId=$this->product->company_id;
        StoreSellerProfile::where('company_id',$sellerId)->update(['tax_profile'=>json_encode(['status'=>'not_registered','mode'=>'none','rate_bps'=>0,'basis'=>'Declaration'])]);
        $other=Company::factory()->create();
        $subscription=CompanySubscription::where('company_id',$sellerId)->firstOrFail()->replicate();$subscription->company_id=$other->id;$subscription->save();
        $warehouse=Warehouse::factory()->create(['company_id'=>$other->id]);
        $product=$this->product->replicate();$product->company_id=$other->id;$product->slug='mixed-vat-fixture-'.Str::uuid();$product->save();
        $variant=$this->variant->replicate();$variant->company_id=$other->id;$variant->product_id=$product->id;$variant->sku='MIXED-VAT-FIXTURE';$variant->barcode='MIXED-VAT-UNIQUE';$variant->save();
        $stock=$this->stock->replicate();$stock->company_id=$other->id;$stock->warehouse_id=$warehouse->id;$stock->variant_id=$variant->id;$stock->save();
        $shipping=StoreShippingAccount::create(['company_id'=>$other->id,'name'=>'Fixture carrier','driver'=>'manual','is_active'=>true,'cod_beneficiary'=>'seller']);
        StoreShippingRate::create(['shipping_account_id'=>$shipping->id,'governorate_code'=>'cairo','fee_minor'=>5000]);
        StoreSellerProfile::create(['company_id'=>$other->id,'is_enabled'=>true,'warehouse_id'=>$warehouse->id,'shipping_account_id'=>$shipping->id,
            'pickup_address'=>['recipient_name'=>'Seller','phone'=>'01012345678','street'=>'Fixture','city'=>'Cairo','governorate_code'=>'cairo'],
            'tax_profile'=>['status'=>'registered','mode'=>'exclusive','rate_bps'=>1400,'registration_number'=>'VAT-FIXTURE','basis'=>'Registration']]);
        app(StoreSettingsService::class)->save(['tax_mode'=>'per_seller']);
        $this->actingAs($this->customer,'sanctum');
        $payload=$this->payload;$payload['items'][]=['product_id'=>$product->id,'variant_id'=>$variant->id,'quantity'=>1];
        $order=$this->postJson('/api/v1/store/orders',$this->submit($payload))->assertCreated()->json('data');
        $this->assertSame('414.00',$order['total_amount']);
        $this->assertSame('14.00',$order['tax_total']);
        $saved=StoreOrder::findOrFail($order['id']);
        $this->assertSame('0.00',$saved->subOrders->firstWhere('company_id',$sellerId)->tax_total);
        $this->assertSame('14.00',$saved->subOrders->firstWhere('company_id',$other->id)->tax_total);
    }
    public function test_changed_tax_treatment_invalidates_quote_without_creating_order(): void {
        $this->actingAs($this->customer,'sanctum');$payload=$this->submit($this->payload);
        app(StoreSettingsService::class)->save(['tax_mode'=>'exclusive','default_tax_rate_bps'=>1400]);
        $this->postJson('/api/v1/store/orders',$payload)->assertStatus(422)->assertJsonValidationErrors('quote_id');
        $this->assertDatabaseCount('store_orders',0);
    }
    public function test_unconfigured_seller_blocks_checkout_but_does_not_change_publishing(): void {
        app(StoreSettingsService::class)->save(['tax_mode'=>'per_seller']);$this->actingAs($this->customer,'sanctum');
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertStatus(422)->assertJsonValidationErrors('tax_profile');
        $this->assertTrue($this->product->fresh()->is_active_in_store);
    }
    public function test_defect_and_change_of_mind_have_separate_deadlines_and_no_customer_return_fee(): void {
        $order=$this->deliveredOrder();$sub=$order->subOrders->first();$item=$order->items->first();
        $sub->update(['delivered_at'=>now('UTC')->subDays(20)]);
        $data=['item_id'=>$item->id,'operation_key'=>(string)Str::uuid(),'quantity'=>1,'reason'=>'change_of_mind'];
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertStatus(422);
        $data['reason']='defect';$data['operation_key']=(string)Str::uuid();
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertCreated()->assertJsonPath('data.shipping_payer','seller');
        $sub->update(['delivered_at'=>now('UTC')->subDays(31)]);$data['operation_key']=(string)Str::uuid();
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertStatus(422);
    }
    public function test_customer_can_request_return_before_courier_collection_is_recorded(): void {
        $order=$this->deliveredOrder(false);$sub=$order->subOrders->first();
        $data=['item_id'=>$order->items->first()->id,'operation_key'=>(string)Str::uuid(),'quantity'=>1,'reason'=>'change_of_mind'];
        $result=$this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertCreated()->assertJsonPath('data.shipping_payer','seller')->json('data');
        $service=app(\Modules\Store\Services\StoreReturnService::class);$return=\Modules\Store\Models\StoreReturn::findOrFail($result['id']);
        $service->act($return,['action'=>'approve']);
        try{$service->act($return,['action'=>'receive','condition'=>'damaged']);$this->fail('Do not reverse unrecorded collection');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertDatabaseCount('store_refunds',0);}
        $this->seed(\Database\Seeders\AddPermissionsSeeder::class);setPermissionsTeamId($this->customer->active_company_id);$this->customer->givePermissionTo('admin.super');
        $url='/api/v1/admin/store/sub-orders/'.$sub->id.'/collection';
        $proof=['confirmed'=>true,'reference'=>'COD-COLLECTION-FIXTURE'];
        $this->postJson($url,$proof)->assertOk();$this->postJson($url,$proof)->assertOk();
        $this->assertDatabaseCount('store_payment_proofs',1);
        $this->assertNotNull($sub->fresh()->collected_at);
        $service->act($return,['action'=>'receive','condition'=>'damaged']);
        $this->assertSame(12500,$return->fresh()->refund_minor);
        $this->assertDatabaseCount('store_refunds',1);
    }
    public function test_legal_product_exception_does_not_remove_defect_rights(): void {
        $this->product->update(['store_return_policy'=>['allow_change_of_mind'=>false,'allow_defect'=>false,'change_of_mind_exception'=>'custom_made','exception_basis'=>'Fixture made to customer specifications']]);
        $order=$this->deliveredOrder();$data=['item_id'=>$order->items->first()->id,'operation_key'=>(string)Str::uuid(),'quantity'=>1,'reason'=>'change_of_mind'];
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertStatus(422);
        $data['reason']='defect';$data['operation_key']=(string)Str::uuid();
        $this->postJson('/api/v1/store/orders/'.$order->order_number.'/returns',$data)->assertCreated();
    }

    public function test_registered_inclusive_and_product_zero_or_exempt_treatments_do_not_raise_final_price(): void {
        app(StoreSettingsService::class)->save(['tax_mode'=>'per_seller']);
        $profile=StoreSellerProfile::where('company_id',$this->product->company_id)->firstOrFail();
        $tax=['status'=>'registered','mode'=>'included','rate_bps'=>1400,'registration_number'=>'VAT-FIXTURE','basis'=>'Registration','product_rules'=>[]];
        $profile->update(['tax_profile'=>$tax]);$this->actingAs($this->customer,'sanctum');
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.tax_minor',2456)->assertJsonPath('data.total_minor',25000);
        foreach(['zero_rated','exempt'] as $treatment){
            $tax['product_rules']=[['product_id'=>$this->product->id,'treatment'=>$treatment,'rate_bps'=>0,'basis'=>'Fixture product treatment']];$profile->update(['tax_profile'=>$tax]);
            $quote=$this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.tax_minor',0)->assertJsonPath('data.total_minor',25000)->json('data');
            $snapshot=\Modules\Store\Models\StoreCheckoutQuote::findOrFail($quote['id'])->snapshot;
            $this->assertSame($treatment,$snapshot['groups'][0]['items'][0]['tax_snapshot']['treatment']);
        }
    }
}

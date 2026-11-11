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

class StoreLocalDeliveryTest extends TestCase
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

    private function local(array $changes=[]): \Modules\Store\Models\StoreSellerProfile {
        $p=StoreSellerProfile::where('company_id',$this->product->company_id)->firstOrFail();
        Warehouse::withoutGlobalScopes()->whereKey($p->warehouse_id)->update(['latitude'=>30,'longitude'=>31,'location_confirmed'=>true]);
        $p->update(['local_delivery'=>array_replace(['enabled'=>true,'pickup_latitude'=>30.0,'pickup_longitude'=>31.0,'radius_meters'=>2000,'fee_minor'=>2000,'free_above_minor'=>null],$changes)]);
        $this->payload['guest_address']+=['latitude'=>30.005,'longitude'=>31.0,'location_confirmed'=>true];
        $this->actingAs($this->customer,'sanctum');return $p;
    }
    private function localOrder(): StoreOrder {
        $result=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $order=StoreOrder::findOrFail($result['id']);$sub=$order->subOrders->first();
        $service=app(\Modules\Store\Services\StoreOrderService::class);$service->confirm($order,true);$service->sellerReady($sub);
        return $order->fresh();
    }
    public function test_local_delivery_without_carrier_keeps_snapshot_and_never_calls_provider(): void {
        $p=$this->local();\Modules\Store\Models\StoreShippingAccount::query()->update(['is_active'=>false]);$p->update(['shipping_account_id'=>null]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $order=$this->localOrder();$sub=$order->subOrders->first();$shipment=$sub->shipment;
        $this->assertSame('220.00',$order->total_amount);$this->assertSame('seller_local',$shipment->delivery_method);$this->assertNull($shipment->shipping_account_id);
        $this->assertSame(2000,$shipment->quote_snapshot['fee_minor']);$this->assertSame(2000,$shipment->quote_snapshot['local_snapshot']['radius_meters']);
        $this->assertSame('completed',app(\Modules\Store\Services\StoreShipmentService::class)->createForSubOrder($sub->id));
        $this->assertSame('awaiting_local_dispatch',$shipment->fresh()->status);
        $p->update(['local_delivery'=>['enabled'=>false]]);
        $this->assertSame('seller_local',$shipment->fresh()->delivery_method);
        $this->assertSame('completed',app(\Modules\Store\Services\StoreShipmentService::class)->cancel($shipment));
        $this->assertNull($shipment->fresh()->provider_reference);
        app(\Modules\Store\Services\StoreOrderService::class)->cancelSubOrder($sub,'local_cancel');$this->assertSame('0.000000',$this->stock->fresh()->reserved);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
    public function test_outside_radius_uses_carrier_and_missing_location_is_not_treated_as_zero_distance(): void {
        $this->local(['radius_meters'=>100]);
        $quote=$this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->json('data');
        $this->assertSame('carrier',$quote['groups'][0]['delivery_method']);$this->assertSame(5000,$quote['shipping_minor']);
        $this->payload['guest_address']['location_confirmed']=false;
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertStatus(422)->assertJsonValidationErrors('delivery_location');
        $this->payload['guest_address']['location_confirmed']=true;$this->payload['guest_address']['latitude']=100;
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertStatus(422)->assertJsonValidationErrors('guest_address.latitude');
    }
    public function test_no_service_outside_radius_blocks_order_and_names_seller(): void {
        $this->local(['radius_meters'=>100]);StoreShippingAccount::query()->update(['is_active'=>false]);
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertStatus(422)->assertJsonValidationErrors('shipping');$this->assertDatabaseCount('store_orders',0);
    }
    public function test_fee_zero_and_free_threshold_apply_to_this_seller_goods(): void {
        $p=$this->local(['free_above_minor'=>20000]);
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.shipping_minor',0)->assertJsonPath('data.total_minor',20000);
        $this->payload['items'][0]['quantity']=1;
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.shipping_minor',2000);
        $p->update(['local_delivery'=>array_replace($p->local_delivery,['fee_minor'=>0])]);
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.shipping_minor',0);
    }
    public function test_settings_or_address_changes_invalidate_quote_before_reserving_stock(): void {
        $p=$this->local();$payload=$this->submit($this->payload);
        $p->update(['local_delivery_version'=>2,'local_delivery'=>array_replace($p->local_delivery,['radius_meters'=>100])]);
        $this->postJson('/api/v1/store/orders',$payload)->assertStatus(422)->assertJsonValidationErrors('quote_id');
        $p->update(['local_delivery'=>array_replace($p->local_delivery,['radius_meters'=>2000])]);
        $payload=$this->submit($this->payload);$payload['guest_address']['latitude']=30.006;
        $this->postJson('/api/v1/store/orders',$payload)->assertStatus(422)->assertJsonValidationErrors('quote_id');
        $this->assertDatabaseCount('store_orders',0);$this->assertSame('0.000000',$this->stock->fresh()->reserved);
    }
    public function test_cart_requests_location_without_disclosing_pickup_coordinates(): void {
        $this->local();$response=$this->postJson('/api/v1/store/cart/validate',['items'=>$this->payload['items']])->assertOk()->assertJsonPath('data.requires_delivery_location',true);
        $this->assertStringNotContainsString('pickup_latitude',$response->getContent());
    }
    public function test_local_cod_collection_and_return_balance_without_courier_payables_or_cash_boxes(): void {
        $this->local();$order=$this->localOrder();$sub=$order->subOrders->first();$ops=app(\Modules\Store\Services\StoreLocalDeliveryOperations::class);
        $ops->act($sub,'dispatch',['confirmed'=>true,'reference'=>'LOCAL-RUN-1']);$ops->act($sub,'delivered',['confirmed'=>true]);
        $this->assertNull($sub->fresh()->collected_at);
        $ops->act($sub,'collect',['confirmed'=>true,'reference'=>'LOCAL-CASH-1']);$ops->act($sub,'collect',['confirmed'=>true,'reference'=>'LOCAL-CASH-1']);
        $this->assertDatabaseCount('store_payment_proofs',1);$this->assertSame(-2000,\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail()->balance_minor);
        \Modules\Sales\Models\InvoiceType::create(['name'=>'Local delivery fixture','code'=>'sale','context'=>'sales','company_id'=>$sub->company_id]);
        $this->assertSame('completed',app(\Modules\Store\Services\StoreInvoiceBridge::class)->create($sub->id));
        $returnService=app(\Modules\Store\Services\StoreReturnService::class);
        $return=$returnService->request($order,['item_id'=>$order->items->first()->id,'operation_key'=>(string)Str::uuid(),'quantity'=>1,'reason'=>'defect']);
        $returnService->act($return,['action'=>'approve']);$returnService->act($return,['action'=>'receive','condition'=>'saleable']);
        $this->assertSame('completed',$returnService->accounting($return->id));$this->assertSame(11000,$return->fresh()->refund_minor);
        $this->assertSame('9.000000',$this->stock->fresh()->quantity);
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('financial_ledger')->whereIn('sub_account_type',['courier_receivable','courier_payable'])->exists());
        $this->assertEquals(\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','debit')->sum('amount'),\Illuminate\Support\Facades\DB::table('financial_ledger')->where('type','credit')->sum('amount'));
    }
    public function test_local_rto_releases_stock_without_a_carrier_charge(): void {
        $this->local();$order=$this->localOrder();$sub=$order->subOrders->first();$ops=app(\Modules\Store\Services\StoreLocalDeliveryOperations::class);
        $ops->act($sub,'dispatch',['confirmed'=>true,'reference'=>'LOCAL-RTO']);
        $data=['confirmed'=>true,'reference'=>'RETURN-RECEIPT','condition'=>'saleable','reason'=>'customer_refused'];
        $ops->act($sub,'return_to_origin',$data);$ops->act($sub,'return_to_origin',$data);
        $this->assertSame('cancelled',$sub->fresh()->status);$this->assertSame('10.000000',$this->stock->fresh()->quantity);$this->assertSame('0.000000',$this->stock->fresh()->reserved);
        $this->assertDatabaseCount('store_return_to_origins',1);$this->assertFalse(\Illuminate\Support\Facades\DB::table('store_financial_lines')->where('account_code','liability:carrier')->exists());
    }

    private function vendor(array $permissions): User {
        $companyId=$this->product->company_id;setPermissionsTeamId($companyId);
        foreach(['admin.super','admin.company','store.vendor.orders.view','store.vendor.orders.manage'] as $permission)\Spatie\Permission\Models\Permission::findOrCreate($permission,'web');
        $vendor=User::factory()->create(['company_id'=>$companyId,'active_company_id'=>$companyId,'email_verified_at'=>now()]);
        $vendor->companies()->attach($companyId,['status'=>'active']);$vendor->givePermissionTo($permissions);return $vendor;
    }
    public function test_settings_and_operations_are_scoped_and_collection_needs_its_own_permission(): void {
        $this->local();$order=$this->localOrder();$sub=$order->subOrders->first();
        $vendor=$this->vendor(['store.vendor.orders.view','store.vendor.delivery.manage']);$this->actingAs($vendor,'sanctum');
        $this->getJson('/api/v1/vendor/store/local-delivery')->assertForbidden();
        $vendor->givePermissionTo('store.vendor.delivery.settings');
        $this->putJson('/api/v1/vendor/store/local-delivery',['enabled'=>true,'pickup_latitude'=>30,'pickup_longitude'=>31,'radius_km'=>'2.500','fee'=>'15.50','free_above'=>null,'company_id'=>99999])->assertOk()->assertJsonPath('data.settings.radius_meters',2500)->assertJsonPath('data.settings.fee_minor',1550);
        $this->assertSame(2500,StoreSellerProfile::where('company_id',$sub->company_id)->first()->local_delivery['radius_meters']);
        $base='/api/v1/vendor/store/deliveries/'.$sub->id;
        $this->postJson($base.'/dispatch',['confirmed'=>false,'reference'=>'LOCAL-STAFF'])->assertStatus(422);
        $this->postJson($base.'/dispatch',['confirmed'=>true,'reference'=>'LOCAL-STAFF'])->assertOk();
        $this->postJson($base.'/delivered',['confirmed'=>true])->assertOk();
        $this->postJson($base.'/collect',['confirmed'=>true,'reference'=>'CASH-STAFF'])->assertForbidden();
        $vendor->givePermissionTo('store.vendor.delivery.collect');
        $this->postJson($base.'/collect',['confirmed'=>true,'reference'=>'CASH-STAFF'])->assertOk();
        $other=Company::factory()->create();$vendor->update(['active_company_id'=>$other->id]);setPermissionsTeamId($other->id);$vendor->givePermissionTo('store.vendor.delivery.manage');
        $this->postJson($base.'/dispatch',['confirmed'=>true,'reference'=>'OTHER'])->assertForbidden();
    }
    public function test_settings_reject_invalid_values_and_unrelated_carrier_deliveries(): void {
        $this->local();$vendor=$this->vendor(['admin.company']);$this->actingAs($vendor,'sanctum');
        $url='/api/v1/vendor/store/local-delivery';
        $data=['enabled'=>true,'pickup_latitude'=>30,'pickup_longitude'=>31,'radius_km'=>1,'fee'=>'0.00'];
        foreach(['radius_km'=>0,'fee'=>'-1'] as $key=>$invalid){$this->putJson($url,array_replace($data,[$key=>$invalid]))->assertStatus(422);}
        $this->putJson($url,['enabled'=>false])->assertOk()->assertJsonPath('data.settings.enabled',false);
        $this->actingAs($this->customer,'sanctum');$order=$this->localOrder();$sub=$order->subOrders->first();
        $this->assertSame('carrier',$sub->shipment->delivery_method);$this->actingAs($vendor,'sanctum');setPermissionsTeamId($vendor->active_company_id);
        $this->postJson('/api/v1/vendor/store/deliveries/'.$sub->id.'/dispatch',['confirmed'=>true,'reference'=>'INVALID'])->assertStatus(422);
    }
    public function test_mixed_basket_uses_local_and_carrier_independently(): void {
        $profile=$this->local();$this->app['auth']->forgetGuards();\Illuminate\Support\Facades\Auth::forgetGuards();
        $other=Company::factory()->create();
        $subscription=CompanySubscription::where('company_id',$this->product->company_id)->firstOrFail()->replicate();$subscription->company_id=$other->id;$subscription->save();
        $warehouse=Warehouse::factory()->create(['company_id'=>$other->id]);
        $product=$this->product->replicate();$product->company_id=$other->id;$product->slug='mixed-local-'.Str::uuid();$product->save();
        $variant=$this->variant->replicate();$variant->company_id=$other->id;$variant->product_id=$product->id;$variant->sku='LOCAL-MIXED';$variant->barcode='LOCAL-MIXED-BARCODE';$variant->save();
        $stock=$this->stock->replicate();$stock->company_id=$other->id;$stock->warehouse_id=$warehouse->id;$stock->variant_id=$variant->id;$stock->save();
        $carrier=StoreShippingAccount::create(['company_id'=>$other->id,'name'=>'Other carrier','driver'=>'manual','is_active'=>true,'cod_beneficiary'=>'seller']);
        \Modules\Store\Models\StoreShippingRate::create(['shipping_account_id'=>$carrier->id,'governorate_code'=>'cairo','fee_minor'=>5000]);
        StoreSellerProfile::create(['company_id'=>$other->id,'is_enabled'=>true,'warehouse_id'=>$warehouse->id,'shipping_account_id'=>$carrier->id,'pickup_address'=>['recipient_name'=>'Other seller','phone'=>'01012345678','street'=>'Other pickup','city'=>'Cairo','governorate_code'=>'cairo']]);
        $this->actingAs($this->customer,'sanctum');$this->payload['items'][]=['product_id'=>$product->id,'variant_id'=>$variant->id,'quantity'=>1];
        $order=$this->postJson('/api/v1/store/orders',$this->submit($this->payload))->assertCreated()->json('data');
        $this->assertSame('370.00',$order['total_amount']);
        $saved=StoreOrder::findOrFail($order['id']);
        $this->assertSame('seller_local',$saved->subOrders->firstWhere('company_id',$profile->company_id)->shipment->delivery_method);
        $this->assertSame('carrier',$saved->subOrders->firstWhere('company_id',$other->id)->shipment->delivery_method);
    }
    public function test_member_notifications_are_personal_and_revoked_members_do_not_receive_email(): void {
        $this->local();$vendor=$this->vendor(['store.vendor.orders.view']);$second=$this->vendor(['store.vendor.orders.view']);
        $this->actingAs($this->customer,'sanctum');$order=$this->localOrder();
        $entry=\Modules\Store\Models\StoreOutbox::where('kind','order.vendor_notification')->where('deduplication_key','order:'.$order->id.':vendor:'.$this->product->company_id)->firstOrFail();
        $service=app(\Modules\Store\Services\StoreNotificationService::class);$service->vendor($entry);$service->vendor($entry);
        $this->assertSame(2,\Illuminate\Support\Facades\DB::table('store_notifications')->where('company_id',$this->product->company_id)->whereNotNull('user_id')->count());
        $this->assertSame(2,\Modules\Store\Models\StoreOutbox::where('kind','order.vendor_member_notification')->count());
        $mail=\Modules\Store\Models\StoreOutbox::where('kind','order.vendor_member_notification')->get()->first(fn($e)=>$e->payload['user_id']===$vendor->id);
        $vendor->companies()->updateExistingPivot($this->product->company_id,['status'=>'suspended']);
        $this->mock(\Modules\Notification\Services\WorkflowProcessor::class)->shouldNotReceive('sendTransactionalEmail');
        $this->assertSame('completed',$service->vendorMember($mail));
        $this->actingAs($second,'sanctum');setPermissionsTeamId($second->active_company_id);
        $this->getJson('/api/v1/vendor/store/notifications')->assertOk()->assertJsonCount(1,'data.notifications.data');
    }

    public function test_warehouse_point_is_the_source_and_changes_require_a_new_quote(): void {
        $profile=$this->local();$warehouse=Warehouse::withoutGlobalScopes()->findOrFail($profile->warehouse_id);$warehouse->update(['location'=>'Warehouse actual street']);
        $payload=$this->submit($this->payload);$quote=\Modules\Store\Models\StoreCheckoutQuote::findOrFail($payload['quote_id']);
        $this->assertSame('Warehouse actual street',$quote->snapshot['groups'][0]['shipping']['pickup_address']['street']);
        $this->assertEquals(30.0,$quote->snapshot['groups'][0]['shipping']['pickup_address']['latitude']);
        $warehouse->update(['latitude'=>30.001]);
        $this->postJson('/api/v1/store/orders',$payload)->assertStatus(422)->assertJsonValidationErrors('quote_id');$this->assertDatabaseCount('store_orders',0);
    }
    public function test_pickup_override_is_scoped_audited_and_keeps_route_price_and_stock_warehouse(): void {
        $profile=$this->local();$order=$this->localOrder();$sub=$order->subOrders->first();$warehouse=Warehouse::withoutGlobalScopes()->findOrFail($profile->warehouse_id);
        $vendor=$this->vendor(['store.vendor.orders.view']);$this->actingAs($vendor,'sanctum');
        $address=$sub->shipment->pickup_address;$address['latitude']=30.5;$address['longitude']=31.5;$address['street']='Order-only pickup';
        $data=['address'=>$address,'reason'=>'Pickup at loading point','confirmed'=>true];$url='/api/v1/vendor/store/orders/'.$sub->id.'/pickup';
        $this->patchJson($url,$data)->assertForbidden();$vendor->givePermissionTo('store.vendor.delivery.pickup');
        $this->patchJson($url,$data)->assertOk()->assertJsonPath('data.pickup.current.street','Order-only pickup');
        $shipment=$sub->shipment->fresh();$this->assertSame('seller_local',$shipment->delivery_method);$this->assertSame('220.00',$order->fresh()->total_amount);
        $this->assertSame(30.0,$warehouse->fresh()->latitude);$this->assertEquals(30.0,$shipment->quote_snapshot['pickup_address']['latitude']);
        $this->assertSame($warehouse->id,$shipment->quote_snapshot['warehouse_id']);$this->assertSame($vendor->id,$shipment->pickup_override['actor_id']);
        $this->assertSame('Order-only pickup',$shipment->pickup_address['street']);
        app(\Modules\Store\Services\StoreLocalDeliveryOperations::class)->act($sub,'dispatch',['confirmed'=>true,'reference'=>'DISPATCH-AFTER-OVERRIDE']);
        $this->patchJson($url,$data)->assertStatus(422);
        $this->assertSame(1,\Illuminate\Support\Facades\DB::table('store_order_events')->where('type','shipment.pickup_overridden')->count());
    }
    public function test_carrier_pickup_can_change_only_before_its_submission_starts(): void {
        $this->actingAs($this->customer,'sanctum');$order=$this->localOrder();$sub=$order->subOrders->first();$vendor=$this->vendor(['admin.company']);$this->actingAs($vendor,'sanctum');
        $address=$sub->shipment->pickup_address;$address['latitude']=30;$address['longitude']=31;$address['location_confirmed']=true;$address['street']='Carrier pickup override';
        $data=['address'=>$address,'reason'=>'New loading location','confirmed'=>true];$url='/api/v1/vendor/store/orders/'.$sub->id.'/pickup';
        $this->patchJson($url,$data)->assertOk();$this->assertSame('carrier',$sub->shipment->fresh()->delivery_method);$this->assertSame('250.00',$order->fresh()->total_amount);
        $sub->shipment->update(['status'=>'creating']);$this->patchJson($url,$data)->assertStatus(422);
    }
    public function test_warehouse_coordinates_remain_optional_and_require_a_complete_confirmed_pair_when_supplied(): void {
        $rules=(new \Modules\Inventory\Http\Requests\StoreWarehouseRequest())->rules();
        $base=['name'=>'Fixture warehouse'];$validator=\Illuminate\Support\Facades\Validator::make($base,$rules);$this->assertFalse($validator->fails());
        foreach([['latitude'=>91,'longitude'=>31,'location_confirmed'=>true],['latitude'=>30],['latitude'=>30,'longitude'=>31]] as $invalid)
            $this->assertTrue(\Illuminate\Support\Facades\Validator::make($base+$invalid,$rules)->fails());
        $this->assertFalse(\Illuminate\Support\Facades\Validator::make($base+['latitude'=>30,'longitude'=>31,'location_confirmed'=>true],$rules)->fails());
    }
    public function test_local_collection_recognizes_remitted_policy_without_inventing_courier_remittance(): void {
        $profile=$this->local();$profile->update(['policy_overrides'=>['commission_timing'=>'remitted']]);$order=$this->localOrder();$sub=$order->subOrders->first();
        $ops=app(\Modules\Store\Services\StoreLocalDeliveryOperations::class);$ops->act($sub,'dispatch',['confirmed'=>true,'reference'=>'TIMING-DISPATCH']);$ops->act($sub,'delivered',['confirmed'=>true]);$ops->act($sub,'collect',['confirmed'=>true,'reference'=>'TIMING-CASH']);
        $this->assertNotNull($sub->fresh()->commission_recognized_at);$this->assertNull($sub->fresh()->remitted_at);
        try{app(\Modules\Store\Services\StorePaymentService::class)->confirmCourierRemittance($sub,'NOT-A-CARRIER',22000);$this->fail('Local delivery has no carrier remittance');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('remittance',$error->errors());}
    }

    public function test_exact_radius_boundary_is_local_and_display_rounding_does_not_expand_it(): void {
        $profile=$this->local(['radius_meters'=>1000]);Warehouse::withoutGlobalScopes()->whereKey($profile->warehouse_id)->update(['latitude'=>0,'longitude'=>0,'location_confirmed'=>true]);
        $this->payload['guest_address']['latitude']=0;$this->payload['guest_address']['longitude']=rad2deg(1000/6371000);
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.groups.0.delivery_method','seller_local');
        $this->payload['guest_address']['longitude']+=0.000001;
        $this->postJson('/api/v1/store/checkout/quote',$this->payload)->assertOk()->assertJsonPath('data.groups.0.delivery_method','carrier');
    }
    public function test_local_activation_requires_confirmed_warehouse_coordinates_not_a_profile_pin(): void {
        $profile=$this->local();Warehouse::withoutGlobalScopes()->whereKey($profile->warehouse_id)->update(['latitude'=>null,'longitude'=>null,'location_confirmed'=>false]);
        $vendor=$this->vendor(['admin.company']);$this->actingAs($vendor,'sanctum');
        $this->putJson('/api/v1/vendor/store/local-delivery',['enabled'=>true,'radius_km'=>2,'fee'=>'0.00','pickup_latitude'=>30,'pickup_longitude'=>31])->assertStatus(422)->assertJsonValidationErrors('warehouse');
        $this->assertNull(Warehouse::withoutGlobalScopes()->findOrFail($profile->warehouse_id)->latitude);
    }

    public function test_online_local_fee_is_allocated_to_seller_and_settled_without_a_carrier(): void {
        $this->local();\Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*'=>function($request){
            if(str_ends_with($request->url(),'/checkout/sessions')&&$request->method()==='POST')return \Illuminate\Support\Facades\Http::response(['id'=>'cs_local','url'=>'https://checkout.stripe.com/c/pay/cs_local']);
            if(str_ends_with($request->url(),'/checkout/sessions/cs_local')){$tx=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->firstOrFail();return \Illuminate\Support\Facades\Http::response(['id'=>'cs_local','client_reference_id'=>(string)$tx->id,'amount_total'=>22000,'currency'=>'egp','livemode'=>false,'payment_status'=>'paid','payment_intent'=>'pi_local']);}
            return \Illuminate\Support\Facades\Http::response([],500);
        }]);
        app(StoreSettingsService::class)->save(['online_enabled'=>true]);
        $gateway=\Modules\Payment\Models\PaymentGateway::create(['company_id'=>config('app.master_company_id'),'driver'=>'stripe','name'=>'Local payment fixture','is_active'=>true,'is_test_mode'=>true,'config'=>['secret_key'=>'sk_test_fixture','webhook_secret'=>'whsec_fixture']]);
        $payload=$this->payload;$payload['payment_method']='online';$payload['payment_gateway_id']=$gateway->id;
        $created=$this->postJson('/api/v1/store/orders',$this->submit($payload))->assertCreated()->json('data');$order=StoreOrder::findOrFail($created['id']);
        $started=$this->postJson('/api/v1/store/orders/'.$order->order_number.'/payment',['payment_gateway_id'=>$gateway->id])->assertOk()->json('data');
        $tx=\Modules\Payment\Models\PaymentTransaction::withoutGlobalScopes()->findOrFail($started['transaction_id']);
        $this->assertTrue(app(\Modules\Payment\Services\PaymentVerificationService::class)->verify($tx)['success']);
        $sub=$order->subOrders->first();$account=\Modules\Store\Models\StoreSellerAccount::where('company_id',$sub->company_id)->firstOrFail();
        $this->assertSame(20000,$account->pending_minor);$this->assertSame(2000,$sub->commission_minor);
        app(\Modules\Store\Services\StoreOrderService::class)->sellerReady($sub);$ops=app(\Modules\Store\Services\StoreLocalDeliveryOperations::class);
        $ops->act($sub,'dispatch',['confirmed'=>true,'reference'=>'ONLINE-LOCAL']);$ops->act($sub,'delivered',['confirmed'=>true]);
        $this->travel(31)->days();app(\Modules\Store\Services\SellerFinanceService::class)->settle($sub->fresh());$this->travelBack();
        $this->assertSame(20000,$account->fresh()->balance_minor);$this->assertSame(0,$account->fresh()->pending_minor);
        $this->assertFalse(\Illuminate\Support\Facades\DB::table('store_financial_lines')->whereIn('account_code',['asset:carrier_receivable','liability:carrier'])->exists());
        \Illuminate\Support\Facades\Http::assertSentCount(2);
    }
}





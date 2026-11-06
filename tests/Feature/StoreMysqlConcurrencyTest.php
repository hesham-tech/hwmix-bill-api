<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Modules\Store\Models\{StoreSellerProfile,StorePayout,StoreOrder,StoreSubOrder,StoreOrderItem,StoreStockAllocation};
class StoreMysqlConcurrencyTest extends TestCase {
 use DatabaseMigrations;
 protected function setUp(): void {
  if(getenv('DB_CONNECTION')!=='mysql')$this->markTestSkipped('Requires an isolated MySQL test database.');
  if(!str_starts_with((string)getenv('DB_DATABASE'),'hwnix_store_test_'))throw new \RuntimeException('Refusing migrations outside an isolated store test database.');
  parent::setUp();
 }
 private function race(string $kind,array $ids,int $warehouse=0): array {
  $barrier=tempnam(sys_get_temp_dir(),'store-race-');unlink($barrier);$workers=[];
  try {
   foreach($ids as $id) {
    $command=[PHP_BINARY,base_path('tests/Support/store-concurrency-worker.php'),$kind,(string)$id,$barrier,(string)$warehouse];
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());
    $this->assertIsResource($process);fclose($pipes[0]);stream_set_timeout($pipes[1],30);
    $workers[]=[$process,$pipes];
   }
   foreach($workers as [$process,$pipes])$this->assertSame("ready\n",fgets($pipes[1]));
   file_put_contents($barrier,'go');$results=[];
   foreach($workers as [$process,$pipes]) {
    $results[]=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $this->assertSame(0,proc_close($process),$errors);
   }
   sort($results);return $results;
  } finally {if(is_file($barrier))unlink($barrier);}
 }
 public function test_parallel_payout_and_stock_requests_cannot_spend_or_reserve_the_same_balance(): void {
  $company=\App\Models\Company::factory()->create();config(['app.master_company_id'=>$company->id]);
  $ledger=app(\Modules\Store\Services\StoreLedgerService::class);$account=$ledger->account($company->id);
  $ledger->post($account,'balance_minor',10000,'fixture:funds','fixture','asset:bank_clearing');
  StoreSellerProfile::create(['company_id'=>$company->id,'payout_destination'=>['bank'=>'fixture','reference'=>'fixture']]);
  $this->assertSame(['accepted','rejected'],$this->race('payout',[$company->id,$company->id]));
  $this->assertSame(10000,$account->refresh()->payout_held_minor);$this->assertSame(1,StorePayout::count());
  $warehouse=\Modules\Inventory\Models\Warehouse::factory()->create(['company_id'=>$company->id]);
  $product=\Modules\Inventory\Models\Product::factory()->create(['company_id'=>$company->id]);
  $variant=\Modules\Inventory\Models\ProductVariant::factory()->create(['company_id'=>$company->id,'product_id'=>$product->id]);
  $stock=\Modules\Inventory\Models\Stock::factory()->create(['company_id'=>$company->id,'warehouse_id'=>$warehouse->id,'variant_id'=>$variant->id,'quantity'=>1,'reserved'=>0,'status'=>'available']);
  $customer=\App\Models\User::factory()->create(['company_id'=>$company->id,'active_company_id'=>$company->id]);$ids=[];
  foreach([1,2] as $number) {
   $order=StoreOrder::create(['order_number'=>'RACE-'.$number,'customer_user_id'=>$customer->id,'payment_method'=>'cod','subtotal'=>100,'total_amount'=>100]);
   $sub=StoreSubOrder::create(['store_order_id'=>$order->id,'company_id'=>$company->id,'sub_order_number'=>'RACE-SUB-'.$number,'subtotal'=>100]);
   StoreOrderItem::create(['store_order_id'=>$order->id,'store_sub_order_id'=>$sub->id,'company_id'=>$company->id,'product_variant_id'=>$variant->id,'product_name_snapshot'=>'fixture','unit_price'=>100,'quantity'=>1,'total_price'=>100,'base_quantity'=>1]);
   $ids[]=$sub->id;
  }
  $this->assertSame(['accepted','rejected'],$this->race('stock',$ids,$warehouse->id));
  $this->assertSame('1.000000',$stock->refresh()->reserved);$this->assertSame(1,StoreStockAllocation::count());
 }
}

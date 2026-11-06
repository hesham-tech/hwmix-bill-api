<?php
namespace Tests\Feature;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,DB};
use Modules\Store\Models\{StoreSellerProfile,StorePayout};
use Modules\Store\Services\{StoreLedgerService,StorePayoutService,StoreOutboxService,StoreOutboxProcessor};
use Tests\TestCase;
class StoreSchedulingTest extends TestCase {
    use RefreshDatabase;
    public function test_financial_outbox_work_precedes_bulk_erp_reminders(): void {
        config(['queue.default'=>'sync']);
        $outbox=app(StoreOutboxService::class);
        $reminder=$outbox->enqueue('notification.erp_scheduled','fixture:bulk-reminder',[]);
        $invoice=$outbox->enqueue('invoice.create','fixture:priority-invoice',['sub_order_id'=>42]);
        $this->mock(\Modules\Store\Services\StoreInvoiceBridge::class)->shouldReceive('create')->with(42)->once()->andReturn('completed');
        $this->assertSame(1,app(StoreOutboxProcessor::class)->drain(1,3));
        $this->assertSame('completed',$invoice->refresh()->status);
        $this->assertSame('pending',$reminder->refresh()->status);
    }
    public function test_laravel_scheduler_actually_registers_store_processing_and_configurable_backups(): void {
        // A fresh CLI process matches Cron and avoids the test kernel's repeated Artisan-start callbacks.
        $process=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('artisan'),'schedule:list','--json'],base_path());
        $process->setTimeout(30);$process->run();
        $this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
        $events=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
        foreach(['store:tick','app:scheduled-backup'] as $command) {
            $matches=array_values(array_filter($events,fn($event)=>str_contains($event['command'],$command)));
            $this->assertCount(1,$matches);
            $this->assertSame('* * * * *',$matches[0]['expression']);
        }
    }
    public function test_async_readiness_requires_a_fresh_worker_probe_and_ignores_stale_queued_probes(): void {
        config(['queue.default'=>'database']);
        \Modules\Store\Models\StoreSetting::updateOrCreate(['key'=>'operations_heartbeat'],['value'=>['completed_at'=>now('UTC')->toIso8601String()]]);
        $health=app(\Modules\Store\Services\StoreOperationalHealth::class);
        $this->assertCount(1,$health->issues());
        (new \Modules\Store\Jobs\ProbeStoreQueue('database',time()-600))->handle();
        $this->assertCount(1,$health->issues());
        (new \Modules\Store\Jobs\ProbeStoreQueue('database',time()))->handle();
        $this->assertSame([],$health->issues());
        config(['queue.default'=>'redis']);$this->assertCount(1,$health->issues());
        config(['queue.default'=>'sync']);$this->assertSame([],$health->issues());
    }
    public function test_production_checkout_stops_when_its_scheduler_is_not_running(): void {
        $this->app->instance('env','production');config(['queue.default'=>'sync']);
        try {app(\Modules\Store\Services\StoreOperationalHealth::class)->assertAcceptingOrders();$this->fail('An unhealthy scheduler must stop new checkout.');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame(503,$error->getStatusCode());}
    }
    public function test_scan_reaches_later_sellers_and_catches_up_a_missed_weekly_payout(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 15:00:00','Africa/Cairo'));
        foreach([false,false,true] as $enabled) {
            $company=Company::factory()->create();$account=app(StoreLedgerService::class)->account($company->id);
            $account->update(['created_at'=>now('UTC')->subMonth()]);
            app(StoreLedgerService::class)->post($account,'balance_minor',10000,'fixture:'.$account->id,'sale','asset:bank');
            StoreSellerProfile::create(['company_id'=>$company->id,'payout_destination'=>['bank_name'=>'Fixture','account_holder'=>'Fixture','account_number'=>'123'],
                'policy_overrides'=>['automatic_payout'=>$enabled,'payout_weekday'=>1,'payout_hour'=>12,'minimum_payout_minor'=>1000]]);
        }
        $service=app(StorePayoutService::class);
        $this->assertSame(0,$service->createScheduledRequests(2));
        $this->assertSame(1,$service->createScheduledRequests(2));
        $this->assertSame(10000,StorePayout::firstOrFail()->amount_minor);
        $this->assertSame(0,$service->createScheduledRequests(2));
        $this->assertDatabaseCount('store_payouts',1);
        $this->travelBack();
    }
    public function test_async_delivery_has_a_recoverable_lease_and_does_not_enqueue_again_before_it_expires(): void {
        Queue::fake();config(['queue.default'=>'database']);
        $entry=app(StoreOutboxService::class)->enqueue('fixture.unknown','fixture:queued',[]);
        $processor=app(StoreOutboxProcessor::class);
        $this->assertSame(1,$processor->drain(3,2));
        $this->assertSame('queued',$entry->refresh()->status);
        $this->assertSame(0,$processor->drain(3,2));
        Queue::assertPushed(\Modules\Store\Jobs\ProcessStoreOutboxEntry::class,1);
        $this->travel(6)->minutes();
        $this->assertSame(1,$processor->drain(3,2));
        Queue::assertPushed(\Modules\Store\Jobs\ProcessStoreOutboxEntry::class,2);
        $processor->processId($entry->id);
        $this->assertSame('manual_review',$entry->refresh()->status);
        $this->assertFalse($processor->processId($entry->id));
        $this->travelBack();
    }
}

<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\Models\{Company,User};
use Modules\Notification\Models\{NotificationWorkflow,NotificationTemplate,MailSetting};
use Modules\Notification\Services\{ErpScheduledWorkflowDispatcher,NotificationIntegrationResolver};
use Modules\Sales\Models\Invoice;
use Modules\Store\Models\StoreOutbox;
use Modules\Store\Services\StoreOutboxProcessor;
class ErpScheduledNotificationTest extends TestCase {
 use RefreshDatabase;
 private function fixture(): array {
  $company=Company::factory()->create();config(['app.master_company_id'=>$company->id]);
  $customer=User::factory()->create(['company_id'=>$company->id,'active_company_id'=>$company->id,'full_name'=>'<b>Customer</b>','nickname'=>null]);
  $template=NotificationTemplate::create(['company_id'=>$company->id,'name'=>'Reminder','channel'=>'email','subject'=>'Invoice {invoice_number}','body'=>'<p>{customer_name}</p>','is_active'=>true]);
  $workflow=NotificationWorkflow::create(['company_id'=>$company->id,'event_type'=>'invoice.overdue','is_active'=>true]);
  $workflow->steps()->create(['step_number'=>1,'delay_days'=>0,'channel'=>['email'],'template_id'=>$template->id,'is_active'=>true]);
  $invoice=Invoice::withoutEvents(fn()=>Invoice::factory()->create(['company_id'=>$company->id,'user_id'=>$customer->id,'created_by'=>$customer->id,
   'status'=>'confirmed','payment_status'=>'unpaid','gross_amount'=>'100.00','net_amount'=>'100.00','total_discount'=>0,'total_tax'=>0,
   'remaining_amount'=>'100.00','due_date'=>now(config('store.business_timezone', 'Africa/Cairo'))->toDateString(),'is_marketplace_managed'=>false]));
  return [$company,$invoice];
 }
 public function test_missed_day_is_recovered_and_repeated_scans_do_not_repeat_delivery(): void {
  $this->travelTo(now('Africa/Cairo')->startOfDay()->addHours(12));
  [$company,$invoice]=$this->fixture();$service=app(ErpScheduledWorkflowDispatcher::class);
  $service->scan();$this->assertDatabaseCount('store_outbox',1);
  $this->travel(2)->days();$service->scan();$this->assertDatabaseCount('store_outbox',1);
  $transport=Mail::mailer('array')->getSymfonyTransport();$entry=StoreOutbox::firstOrFail();
  $this->assertTrue(app(StoreOutboxProcessor::class)->processId($entry->id));
  $this->assertCount(1,$transport->messages());
  $this->assertStringContainsString('&lt;b&gt;Customer&lt;/b&gt;',$transport->messages()->first()->getOriginalMessage()->getHtmlBody());
  $service->scan();$this->assertFalse(app(StoreOutboxProcessor::class)->processId($entry->id));$this->assertCount(1,$transport->messages());
  $this->travelBack();
 }
 public function test_paid_invoice_is_rechecked_before_queued_reminder_is_sent(): void {
  [$company,$invoice]=$this->fixture();app(ErpScheduledWorkflowDispatcher::class)->scan();
  $invoice->updateQuietly(['payment_status'=>'paid','remaining_amount'=>0]);
  $transport=Mail::mailer('array')->getSymfonyTransport();app(StoreOutboxProcessor::class)->processId(StoreOutbox::firstOrFail()->id);
  $this->assertCount(0,$transport->messages());
 }
 public function test_private_master_or_other_tenant_mail_is_not_inherited(): void {
  $master=Company::factory()->create();$tenant=Company::factory()->create();$other=Company::factory()->create();config(['app.master_company_id'=>$master->id]);
  $setting=MailSetting::create(['company_id'=>$master->id,'mail_transport'=>'smtp','mail_host'=>'smtp.fixture.invalid','mail_port'=>587,'mail_from_address'=>'sender@example.test','mail_from_name'=>'Fixture','is_global'=>false,'is_active'=>true]);
  $resolver=app(NotificationIntegrationResolver::class);$this->assertNull($resolver->mail($tenant->id));
  $setting->update(['is_global'=>true]);$this->assertSame($setting->id,$resolver->mail($tenant->id)?->id);
  $setting->update(['company_id'=>$other->id]);$this->assertNull($resolver->mail($tenant->id));
 }
}

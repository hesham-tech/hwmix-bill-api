<?php
namespace Tests\Feature;
use App\Models\{Company,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Notification\Models\{NotificationTemplate,NotificationWorkflow};
use Modules\Notification\Services\StoreWorkflowDelivery;
use Modules\Store\Models\StoreOutbox;
use Modules\Store\Services\StoreOutboxProcessor;
use Tests\TestCase;
class StoreNotificationWorkflowTest extends TestCase {
    use RefreshDatabase;
    private function setupWorkflow(int $days=0): array {
        $company=Company::factory()->create();config(['app.master_company_id'=>$company->id]);
        $user=User::factory()->create(['full_name'=>'<b>Buyer</b>','email_verified_at'=>now()]);
        $template=NotificationTemplate::create(['company_id'=>$company->id,'name'=>'Store fixture','channel'=>'email','subject'=>'Order {order_number}','body'=>'<p>{customer_name} {order_number}</p>','is_active'=>true]);
        $workflow=NotificationWorkflow::create(['company_id'=>$company->id,'event_type'=>'store.customer.created','is_active'=>true]);
        $step=$workflow->steps()->create(['step_number'=>1,'delay_days'=>$days,'channel'=>['email'],'template_id'=>$template->id,'is_active'=>true]);
        return [$company,$user,$step];
    }
    public function test_store_uses_existing_templates_with_durable_delays_and_escaped_variables(): void {
        [$company,$user]=$this->setupWorkflow(1);$bridge=app(StoreWorkflowDelivery::class);
        $vars=['{customer_name}'=>'Buyer','{order_number}'=>'<img src=x>'];
        $this->assertTrue($bridge->queue('store.customer.created','customer',$user->id,$company->id,$vars,'fixture:created'));
        $bridge->queue('store.customer.created','customer',$user->id,$company->id,$vars,'fixture:created');
        $this->assertDatabaseCount('store_outbox',1);$entry=StoreOutbox::firstOrFail();$processor=app(StoreOutboxProcessor::class);
        $transport=Mail::mailer('array')->getSymfonyTransport();
        $this->assertFalse($processor->processId($entry->id));
        $entry->update(['available_at'=>now()->subSecond()]);$this->assertTrue($processor->processId($entry->id));
        $this->assertSame('completed',$entry->refresh()->status);
        $messages=$transport->messages();$this->assertCount(1,$messages);
        $message=$messages->last()->getOriginalMessage();
        $this->assertStringContainsString('&lt;b&gt;Buyer&lt;/b&gt;',$message->getHtmlBody());
        $this->assertStringContainsString('&lt;img src=x&gt;',$message->getHtmlBody());
        $this->assertFalse($processor->processId($entry->id));$this->assertCount(1,Mail::mailer('array')->getSymfonyTransport()->messages());
    }
    public function test_changed_email_is_unverified_and_a_delayed_notice_is_not_sent_to_the_old_address(): void {
        [$company,$user]=$this->setupWorkflow();
        app(StoreWorkflowDelivery::class)->queue('store.customer.created','customer',$user->id,$company->id,['{order_number}'=>'ORDER'],'fixture:changed');
        $user->update(['email'=>'changed@example.test']);$this->assertNull($user->refresh()->email_verified_at);
        app(StoreOutboxProcessor::class)->processId(StoreOutbox::firstOrFail()->id);
        $this->assertCount(0,Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}

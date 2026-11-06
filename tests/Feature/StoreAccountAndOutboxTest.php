<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Modules\Store\Models\{StoreEmailVerification,StoreOutbox};
use Modules\Store\Services\{StoreAccountService,StoreOutboxService,StoreOutboxProcessor};
use Tests\TestCase;
class StoreAccountAndOutboxTest extends TestCase {
    use RefreshDatabase;
    public function test_email_attempt_limit_is_persisted_and_code_is_not_returned_or_stored_in_plaintext(): void {
        Queue::fake();
        $user=User::factory()->create(['email_verified_at'=>null]);
        $service=app(StoreAccountService::class);
        $response=$service->request($user);
        $verification=StoreEmailVerification::where('user_id',$user->id)->firstOrFail();
        $entry=StoreOutbox::where('kind','account.email_verification')->firstOrFail();
        $code=$entry->payload['code'];
        $this->assertArrayNotHasKey('code',$response);
        $this->assertNotSame($code,$verification->getRawOriginal('token_hash'));
        $this->assertStringNotContainsString($code,$entry->getRawOriginal('payload'));
        for($attempt=0;$attempt<5;$attempt++) {
            try { $service->verify($user,'00000000'); $this->fail('Invalid code was accepted.'); }
            catch(ValidationException) {}
        }
        $this->assertSame(5,$verification->refresh()->attempts);
        $this->expectException(ValidationException::class);
        $service->verify($user,$code);
    }
    public function test_valid_code_verifies_the_existing_customer_without_creating_another_identity(): void {
        Queue::fake();
        $user=User::factory()->create(['email_verified_at'=>null]);
        $count=User::withoutGlobalScopes()->count();
        $service=app(StoreAccountService::class);
        $service->request($user);
        $code=StoreOutbox::where('kind','account.email_verification')->firstOrFail()->payload['code'];
        $verified=$service->verify($user,$code);
        $this->assertNotNull($verified->email_verified_at);
        $this->assertSame($count,User::withoutGlobalScopes()->count());
    }
    public function test_outbox_does_not_execute_future_tasks_and_repeated_enqueues_are_deduplicated(): void {
        $outbox=app(StoreOutboxService::class);
        $first=$outbox->enqueue('unknown.example','example:one',['sample'=>true],now()->addHour());
        $again=$outbox->enqueue('unknown.example','example:one',['sample'=>true],now()->addHour());
        $this->assertSame($first->id,$again->id);
        $this->assertFalse(app(StoreOutboxProcessor::class)->processId($first->id));
        $this->assertSame(0,$first->refresh()->attempts);
    }

    public function test_verification_outbox_delivers_the_code_once_through_the_shared_mailer(): void {
        Queue::fake();
        $user=User::factory()->create(['email_verified_at'=>null]);
        app(StoreAccountService::class)->request($user);
        $entry=StoreOutbox::where('kind','account.email_verification')->firstOrFail();
        $transport=\Illuminate\Support\Facades\Mail::mailer('array')->getSymfonyTransport();
        $processor=app(StoreOutboxProcessor::class);
        $this->assertTrue($processor->processId($entry->id));
        $this->assertCount(1,$transport->messages());
        $message=$transport->messages()->first()->getOriginalMessage();
        $this->assertSame($user->email,$message->getTo()[0]->getAddress());
        $this->assertStringContainsString($entry->payload['code'],$message->getHtmlBody());
        $this->assertFalse($processor->processId($entry->id));
        $this->assertCount(1,$transport->messages());
    }
}

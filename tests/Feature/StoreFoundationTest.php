<?php
namespace Tests\Feature;
use App\Models\{Company,Plan,CompanySubscription};
use App\Services\SaaS\SubscriptionFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Store\Models\StoreSellerProfile;
use Modules\Store\Services\{StoreLedgerService,SellerPolicyService};
use Tests\TestCase;

class StoreFoundationTest extends TestCase {
    use RefreshDatabase;
    public function test_topup_pays_debt_and_preserves_separate_prepaid_balance_idempotently(): void {
        $company=Company::factory()->create();
        $ledger=app(StoreLedgerService::class);
        $account=$ledger->account($company->id);
        $ledger->post($account,'balance_minor',-500,'test:debt','commission','income:commission');
        $ledger->topUp($company->id,1000,'test-topup-1','bank-reference-1');
        $ledger->topUp($company->id,1000,'test-topup-1','bank-reference-1');
        $ledger->topUp($company->id,1000,'test-topup-new-page',' BANK-reference-1 ');
        $account->refresh();
        $this->assertSame(0,$account->balance_minor);
        $this->assertSame(500,$account->prepaid_minor);
        $this->assertSame(0,$account->pending_minor);
        $unbalanced=DB::table('store_financial_lines')->select('journal_id')->groupBy('journal_id')->havingRaw('SUM(amount_minor) <> 0')->get();
        $this->assertCount(0,$unbalanced);
        $this->assertDatabaseCount('store_seller_ledger',3);
    }
    public function test_financial_operation_key_cannot_be_reused_with_other_amount(): void {
        $company=Company::factory()->create();
        $ledger=app(StoreLedgerService::class);
        $account=$ledger->account($company->id);
        $ledger->post($account,'prepaid_minor',100,'test:credit','topup','asset:bank');
        $this->expectException(\LogicException::class);
        $ledger->post($account,'prepaid_minor',200,'test:credit','topup','asset:bank');
    }
    public function test_partial_feature_overrides_inherit_other_plan_flags_and_respect_false(): void {
        $plan=new Plan(['features'=>['flags'=>['a'=>true,'b'=>true]]]);
        $sub=new CompanySubscription(['features'=>['flags'=>['a'=>false]]]);
        $sub->setRelation('plan',$plan);
        $this->assertFalse(SubscriptionFeatures::has($sub,'a'));
        $this->assertTrue(SubscriptionFeatures::has($sub,'b'));
        $sub->feature_overrides=['b'=>false];
        $this->assertFalse(SubscriptionFeatures::has($sub,'b'));
    }
    public function test_future_subscription_cannot_publish_and_explicit_false_matches_sql_scope(): void {
        $company=Company::factory()->create();
        $plan=Plan::create(['name'=>'Store','code'=>'store-test','price'=>0,'currency'=>'EGP',
            'duration'=>1,'duration_unit'=>'months','is_active'=>true,'features'=>['flags'=>['store_publish'=>true]]]);
        $sub=CompanySubscription::create(['company_id'=>$company->id,'plan_id'=>$plan->id,'status'=>'active',
            'starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth(),'features'=>[]]);
        $this->assertTrue($company->canPublishToStore());
        $this->assertTrue(Company::withoutGlobalScopes()->whereKey($company->id)->storePublishEnabled()->exists());
        $sub->update(['feature_overrides'=>['store_publish'=>false]]);
        $this->assertFalse($company->canPublishToStore());
        $this->assertFalse(Company::withoutGlobalScopes()->whereKey($company->id)->storePublishEnabled()->exists());
        $sub->update(['feature_overrides'=>null,'starts_at'=>now()->addDay()]);
        $this->assertFalse($sub->isActive());
        $this->assertFalse(Company::withoutGlobalScopes()->whereKey($company->id)->storePublishEnabled()->exists());
    }
    public function test_company_policy_zero_and_false_override_plan_without_losing_inheritance(): void {
        $company=Company::factory()->create();
        $plan=Plan::create(['name'=>'Flexible','code'=>'flexible-test','price'=>0,'currency'=>'EGP',
            'duration'=>1,'duration_unit'=>'months','is_active'=>true,
            'features'=>['store_policy'=>['funding_mode'=>'credit','credit_limit_minor'=>5000,'automatic_payout'=>true]]]);
        CompanySubscription::create(['company_id'=>$company->id,'plan_id'=>$plan->id,'status'=>'active',
            'starts_at'=>now()->subDay(),'ends_at'=>now()->addMonth()]);
        StoreSellerProfile::create(['company_id'=>$company->id,'policy_overrides'=>['credit_limit_minor'=>0,'automatic_payout'=>false]]);
        $policy=app(SellerPolicyService::class)->resolve($company->id);
        $this->assertSame(0,$policy['credit_limit_minor']);
        $this->assertFalse($policy['automatic_payout']);
        $this->assertSame('credit',$policy['funding_mode']);
    }

    public function test_a_bank_transfer_reference_cannot_pay_two_different_payouts(): void {
        $company=Company::factory()->create();$ledger=app(StoreLedgerService::class);$account=$ledger->account($company->id);
        $ledger->post($account,'balance_minor',10000,'fixture:available','settlement','asset:bank');
        StoreSellerProfile::create(['company_id'=>$company->id,'payout_destination'=>['bank_name'=>'Fixture bank','account_holder'=>'Fixture','account_number'=>'123']]);
        $service=app(\Modules\Store\Services\StorePayoutService::class);
        $first=$service->request($company->id,2000,'fixture:payout1');$second=$service->request($company->id,2000,'fixture:payout2');
        $service->approve($first);$service->approve($second);$service->paid($first,'BANK-FULL-REFERENCE');
        $service->paid($first,'BANK-FULL-REFERENCE');
        try {$service->paid($second,' bank-full-reference ');$this->fail('One bank transfer must not pay two requests.');}
        catch(\Illuminate\Validation\ValidationException $expected){}
        $this->assertSame(8000,$account->refresh()->balance_minor);$this->assertSame(2000,$account->payout_held_minor);
        $this->assertSame('approved',$second->refresh()->status);$this->assertDatabaseCount('store_payment_proofs',1);
    }
}

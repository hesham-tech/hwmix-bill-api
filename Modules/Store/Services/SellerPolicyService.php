<?php
namespace Modules\Store\Services;
use App\Models\Company;
use Modules\Store\Models\StoreSellerProfile;

class SellerPolicyService
{
    public function __construct(private StoreSettingsService $settings) {}
    public function resolve(int $companyId): array {
        $company=Company::withoutGlobalScopes()->findOrFail($companyId);
        $subscription=$company->subscriptions()->whereIn('status',['active','trial'])
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))
            ->where(fn($q)=>$q->where('status','!=','trial')->orWhereNull('trial_ends_at')->orWhere('trial_ends_at','>',now()))
            ->with('plan')->latest('id')->first();
        $plan=$subscription?->plan;
        $trusted=$plan && ($plan->company_id===null||(int)$plan->company_id===(int)config('app.master_company_id',1));
        $planPolicy=$trusted?array_filter($plan->features['store_policy']??[],fn($v)=>$v!==null):[];
        $overrides=StoreSellerProfile::where('company_id',$companyId)->value('policy_overrides')??[];
        // Null means inherit; zero and false are explicit values.
        $overrides=array_filter($overrides,fn($v)=>$v!==null);
        $defaults=$this->settings->get()['seller_policy'];
        return array_replace($defaults,array_intersect_key($planPolicy,$defaults),array_intersect_key($overrides,$defaults));
    }
    public function hash(array $policy): string {
        ksort($policy);
        return hash('sha256',json_encode($policy,JSON_THROW_ON_ERROR));
    }
    public function canPublish(int $companyId): bool {
        $company=Company::withoutGlobalScopes()->find($companyId);
        return $company && $company->canPublishToStore();
    }
}

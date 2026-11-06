<?php
namespace App\Services\SaaS;
use App\Models\CompanySubscription;
use Illuminate\Database\Eloquent\Builder;

final class SubscriptionFeatures
{
    /** Preserve explicit legacy differences while refreshing inherited plan values. */
    public static function differences(array $values,array $base): array {
        $result=[];
        foreach($values as $key=>$value) {
            if(is_array($value)&&is_array($base[$key]??null)) {
                $nested=self::differences($value,$base[$key]); if($nested!==[]) $result[$key]=$nested;
            } elseif(!array_key_exists($key,$base)||$value!==$base[$key]) $result[$key]=$value;
        }
        return $result;
    }
    public static function has(CompanySubscription $subscription,string $key): bool {
        $value=false;
        foreach([$subscription->plan?->features??[],$subscription->features??[],$subscription->feature_overrides??[]] as $source) {
            if(is_string($source)) $source=json_decode($source,true)?:[];
            if(array_key_exists($key,$source['flags']??[])&&$source['flags'][$key]!==null) $value=(bool)$source['flags'][$key];
            elseif(array_key_exists($key,$source)&&$source[$key]!==null) $value=(bool)$source[$key];
        }
        return $value;
    }
    public static function valid(Builder $query): Builder {
        return $query->whereIn('status',['active','trial'])
            ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
            ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',now()))
            ->where(fn($q)=>$q->where('status','!=','trial')->orWhereNull('trial_ends_at')->orWhere('trial_ends_at','>',now()));
    }
    public static function publishingCompanies(Builder $query): Builder {
        return $query->whereNull('companies.deleted_at')->whereHas('subscriptions',function($subscriptions) {
            self::valid($subscriptions);
            $subscriptions->whereHas('plan',fn($plan)=>$plan->whereNull('company_id')->orWhere('company_id',config('app.master_company_id',1)));
            $subscriptions->where('company_subscriptions.id',function($latest) {
                $latest->selectRaw('MAX(cs.id)')->from('company_subscriptions as cs')
                    ->whereColumn('cs.company_id','companies.id')->whereNull('cs.deleted_at')->whereIn('cs.status',['active','trial'])
                    ->where(fn($q)=>$q->whereNull('cs.starts_at')->orWhere('cs.starts_at','<=',now()))
                    ->where(fn($q)=>$q->whereNull('cs.ends_at')->orWhere('cs.ends_at','>',now()))
                    ->where(fn($q)=>$q->where('cs.status','!=','trial')->orWhereNull('cs.trial_ends_at')->orWhere('cs.trial_ends_at','>',now()));
            });
            $paths=['feature_overrides->flags->store_publish','feature_overrides->store_publish','features->flags->store_publish','features->store_publish'];
            $subscriptions->where(function($q) use($paths) {
                foreach($paths as $index=>$path) {
                    $q->orWhere(function($branch) use($paths,$index,$path) {
                        foreach(array_slice($paths,0,$index) as $previous) $branch->whereNull($previous);
                        $branch->where($path,true);
                    });
                }
                $q->orWhere(function($branch) use($paths) {
                    foreach($paths as $path) $branch->whereNull($path);
                    $branch->whereHas('plan',fn($p)=>$p->where('features->flags->store_publish',true)
                        ->orWhere(fn($p2)=>$p2->whereNull('features->flags->store_publish')->where('features->store_publish',true)));
                });
            });
        });
    }
}

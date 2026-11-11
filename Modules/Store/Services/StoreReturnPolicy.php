<?php
namespace Modules\Store\Services;

use Illuminate\Validation\ValidationException;

class StoreReturnPolicy
{
    public const EXCEPTIONS=['perishable','custom_made','nature_or_packaging','unsealed_hygiene'];
    public function resolve(array $defaults,array $product=[]): array {
        $policy=array_replace($defaults,$product);
        $legacy=(int)($policy['window_days']??0);
        $exception=$product['change_of_mind_exception']??null;
        if($exception!==null&&(!in_array($exception,self::EXCEPTIONS,true)||empty($product['exception_basis'])))
            throw ValidationException::withMessages(['return_policy'=>'يلزم سبب قانوني موثق لاستثناء تغيير الرأي.']);
        // Minimum Egyptian consumer rights. Longer promises are retained, including old snapshots.
        return array_replace($policy,[
            'change_of_mind_days'=>max(14,$legacy,(int)($policy['change_of_mind_days']??0)),
            'defect_days'=>max(30,$legacy,(int)($policy['defect_days']??0)),
            'allow_defect'=>true,'allow_change_of_mind'=>$exception===null,
            'change_of_mind_exception'=>$exception,'exception_basis'=>$product['exception_basis']??null,
            'shipping_payer'=>'seller',
        ]);
    }
}

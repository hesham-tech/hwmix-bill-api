<?php
namespace Modules\Store\Services;
final class ShippingFinancials {
    public static function snapshot(array $quote,int $goodsGross,int $commission): array {
        $fee=(int)$quote['fee_minor']; $cost=(int)($quote['carrier_fee_minor']??$fee);
        $platform=$quote['shipping_fee_beneficiary']==='platform';
        $sellerSubsidy=$platform&&($quote['subsidy_payer']??'seller')==='seller'?max(0,$cost-$fee):0;
        return ['seller_net_minor'=>$goodsGross+$fee-($platform?$fee+$sellerSubsidy:0)-$commission,
            'platform_shipping_due_minor'=>$platform?$fee+$sellerSubsidy:0,
            'platform_carrier_cost_minor'=>$platform?$cost:0,'carrier_fee_minor'=>$cost,
            'shipping_margin_minor'=>$platform?max(0,$fee-$cost):0,
            'platform_shipping_subsidy_minor'=>$platform&&($quote['subsidy_payer']??'seller')==='platform'?max(0,$cost-$fee):0,
            'seller_shipping_subsidy_minor'=>$sellerSubsidy,'cod_beneficiary'=>$quote['cod_beneficiary']];
    }
    public static function allocation(array $snapshot,int $direction=1): array {
        return [
            ['account_code'=>'liability:carrier','amount_minor'=>-$direction*(int)($snapshot['platform_carrier_cost_minor']??$snapshot['platform_shipping_due_minor']??0)],
            ['account_code'=>'income:shipping_margin','amount_minor'=>-$direction*(int)($snapshot['shipping_margin_minor']??0)],
            ['account_code'=>'expense:shipping_subsidy','amount_minor'=>$direction*(int)($snapshot['platform_shipping_subsidy_minor']??0)],
        ];
    }
}

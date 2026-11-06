<?php
namespace Tests\Unit;
use Modules\Store\Services\ShippingFinancials;
use PHPUnit\Framework\TestCase;
class ShippingFinancialsTest extends TestCase {
    public function test_free_shipping_is_funded_by_the_selected_party_and_never_erases_carrier_cost(): void {
        foreach(['seller','platform'] as $payer) {
            $s=ShippingFinancials::snapshot(['fee_minor'=>0,'carrier_fee_minor'=>500,'subsidy_payer'=>$payer,'shipping_fee_beneficiary'=>'platform','cod_beneficiary'=>'platform'],10000,1000);
            $this->assertSame($payer==='seller'?8500:9000,$s['seller_net_minor']);
            $this->assertSame(500,$s['platform_carrier_cost_minor']);
            $this->assertSame(0,array_sum(array_column(ShippingFinancials::allocation($s),'amount_minor'))+$s['platform_shipping_due_minor']);
            $this->assertSame(0,array_sum(array_column(ShippingFinancials::allocation($s,-1),'amount_minor'))-$s['platform_shipping_due_minor']);
        }
    }
    public function test_shipping_markup_and_seller_owned_carrier_have_separate_allocations(): void {
        $q=['fee_minor'=>700,'carrier_fee_minor'=>500,'shipping_fee_beneficiary'=>'platform','cod_beneficiary'=>'platform'];
        $s=ShippingFinancials::snapshot($q,10000,1000);
        $this->assertSame(200,$s['shipping_margin_minor']);$this->assertSame(9000,$s['seller_net_minor']);
        $q['shipping_fee_beneficiary']='seller';$q['cod_beneficiary']='seller';
        $s=ShippingFinancials::snapshot($q,10000,1000);
        $this->assertSame(9700,$s['seller_net_minor']);$this->assertSame(0,$s['platform_shipping_due_minor']);$this->assertSame(500,$s['carrier_fee_minor']);
    }
}

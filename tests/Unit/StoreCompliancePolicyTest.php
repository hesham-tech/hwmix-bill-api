<?php
namespace Tests\Unit;
use Modules\Store\Services\{StoreReturnPolicy,StoreTaxService};
use PHPUnit\Framework\TestCase;
class StoreCompliancePolicyTest extends TestCase
{
    public function test_tax_rounding_and_inclusive_price_are_stable_in_minor_units(): void {
        $tax=new StoreTaxService();
        $this->assertSame(['tax'=>1400,'gross'=>11400],$tax->calculate(10000,['mode'=>'exclusive','rate_bps'=>1400]));
        $this->assertSame(['tax'=>1400,'gross'=>11400],$tax->calculate(11400,['mode'=>'included','rate_bps'=>1400]));
        $this->assertSame(['tax'=>0,'gross'=>10000],$tax->calculate(10000,['mode'=>'none','rate_bps'=>0]));
        $this->assertSame(['tax'=>1,'gross'=>6],$tax->calculate(5,['mode'=>'exclusive','rate_bps'=>1400]));
    }
    public function test_old_short_or_disabled_policy_cannot_remove_minimum_defect_rights(): void {
        $policy=(new StoreReturnPolicy())->resolve([],['window_days'=>7,'allow_defect'=>false,'allow_change_of_mind'=>false]);
        $this->assertSame(30,$policy['defect_days']);$this->assertSame(14,$policy['change_of_mind_days']);
        $this->assertTrue($policy['allow_defect']);$this->assertTrue($policy['allow_change_of_mind']);
        $longer=(new StoreReturnPolicy())->resolve([],['window_days'=>60]);
        $this->assertSame(60,$longer['defect_days']);$this->assertSame(60,$longer['change_of_mind_days']);
    }
}

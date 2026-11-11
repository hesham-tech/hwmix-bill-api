<?php
namespace Tests\Unit;
use Modules\Store\Services\{LocalDeliveryService,StoreSnapshot};
use PHPUnit\Framework\TestCase;
class StoreDeliveryGeometryTest extends TestCase {
    public function test_distance_is_in_meters_and_symmetric(): void {
        $this->assertSame(0.0,LocalDeliveryService::distance(30,31,30,31));
        $this->assertEqualsWithDelta(111194.9266,LocalDeliveryService::distance(0,0,0,1),0.001);
        $this->assertEqualsWithDelta(LocalDeliveryService::distance(30,31,30.1,31.1),LocalDeliveryService::distance(30.1,31.1,30,31),0.000001);
    }
    public function test_snapshot_comparison_handles_database_json_key_order_and_float_encoding_without_ignoring_real_changes(): void {
        $this->assertTrue(StoreSnapshot::same(['pickup'=>['latitude'=>30.0,'longitude'=>31.0],'fee'=>1500],['fee'=>1500,'pickup'=>['longitude'=>31,'latitude'=>30]]));
        $this->assertFalse(StoreSnapshot::same(['fee'=>1500],['fee'=>1501]));
        $this->assertFalse(StoreSnapshot::same(['latitude'=>30],['latitude'=>30.0001]));
        $this->assertFalse(StoreSnapshot::same(['fee'=>1500],['fee'=>'1500']));
    }
}

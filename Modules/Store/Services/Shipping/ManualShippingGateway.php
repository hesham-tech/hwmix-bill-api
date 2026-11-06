<?php
namespace Modules\Store\Services\Shipping;
use Modules\Store\Contracts\ShippingGateway;
use Modules\Store\Models\StoreShipment;
class ManualShippingGateway implements ShippingGateway {
    public function __construct(array $configuration=[]) {}
    public function create(StoreShipment $shipment): array { return ['success'=>true,'manual'=>true]; }
    public function track(StoreShipment $shipment): array { return ['success'=>true,'status'=>$shipment->status,'tracking_number'=>$shipment->tracking_number]; }
    public function cancel(StoreShipment $shipment): array { return ['success'=>false,'manual_confirmation_required'=>true]; }
}

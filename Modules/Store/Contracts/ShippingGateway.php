<?php
namespace Modules\Store\Contracts;
use Modules\Store\Models\StoreShipment;
interface ShippingGateway {
    public function create(StoreShipment $shipment): array;
    public function track(StoreShipment $shipment): array;
    public function cancel(StoreShipment $shipment): array;
}

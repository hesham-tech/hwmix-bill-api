<?php
namespace Modules\Store\Services;
use Modules\Store\Models\StoreSellerProfile;
use Modules\Inventory\Models\Warehouse;
class WarehousePickupService {
    public function address(StoreSellerProfile $profile): array {
        $query=Warehouse::withoutGlobalScopes()->where('company_id',$profile->company_id);
        if(\Illuminate\Support\Facades\DB::transactionLevel()>0)$query->lockForUpdate();
        $warehouse=$query->find($profile->warehouse_id);
        return array_replace($profile->pickup_address??[],[
            'street'=>$warehouse?->location?:($profile->pickup_address['street']??''),
            'latitude'=>$warehouse?->location_confirmed?$warehouse->latitude:null,'longitude'=>$warehouse?->location_confirmed?$warehouse->longitude:null,'location_confirmed'=>(bool)$warehouse?->location_confirmed,
        ]);
    }
}

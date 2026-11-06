<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreShipment extends Model
{
    protected $table='store_shipments';
    protected $guarded=[];
    protected $casts=['integration_snapshot'=>'encrypted:array','remittance_snapshot'=>'encrypted:array','pickup_address'=>'array','delivery_address'=>'array','quote_snapshot'=>'array','cod_minor'=>'integer'];
    protected $hidden=['credentials','integration_snapshot','payout_destination','destination_snapshot','payload','remittance_snapshot'];
}

<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreShippingRate extends Model
{
    protected $table='store_shipping_rates';
    protected $guarded=[];
    protected $attributes=['scope_company_id'=>0];
    protected $casts=['fee_minor'=>'integer','carrier_fee_minor'=>'integer','free_above_minor'=>'integer','cod_allowed'=>'boolean','is_active'=>'boolean'];
}

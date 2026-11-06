<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreShippingAccount extends Model
{
    protected $table='store_shipping_accounts';
    protected $guarded=[];
    protected $casts=['credentials'=>'encrypted:array','configuration'=>'array','is_active'=>'boolean','is_test_mode'=>'boolean'];
    protected $hidden=['credentials','integration_snapshot','payout_destination','destination_snapshot','payload'];
    use \Illuminate\Database\Eloquent\SoftDeletes;
}

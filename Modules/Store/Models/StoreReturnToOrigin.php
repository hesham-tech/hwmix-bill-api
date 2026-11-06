<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreReturnToOrigin extends Model {
    protected $table='store_return_to_origins';protected $guarded=[];
    protected $casts=['carrier_cost_minor'=>'integer','received_at'=>'datetime'];
}

<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreOrderEvent extends Model
{
    protected $table='store_order_events';
    protected $guarded=[];
    protected $casts=['metadata'=>'array','created_at'=>'datetime'];
    public const UPDATED_AT=null;
}

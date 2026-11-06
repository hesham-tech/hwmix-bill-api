<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreReturn extends Model
{
    protected $table='store_returns';
    protected $guarded=[];
    protected $casts=['quantity'=>'integer','refund_minor'=>'integer','inspection_snapshot'=>'array','received_at'=>'datetime','completed_at'=>'datetime'];
    public function item() { return $this->belongsTo(StoreOrderItem::class,'store_order_item_id'); }
}

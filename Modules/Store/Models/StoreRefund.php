<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreRefund extends Model
{
    protected $table='store_refunds';
    protected $guarded=[];
    protected $casts=['amount_minor'=>'integer','completed_at'=>'datetime','allocation_snapshot'=>'array'];
}

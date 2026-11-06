<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreStockAllocation extends Model
{
    protected $table='store_stock_allocations';
    protected $guarded=[];
    protected $casts=['quantity'=>'decimal:6'];
}

<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreCheckoutQuote extends Model
{
    protected $table='store_checkout_quotes';
    protected $guarded=[];
    protected $casts=['snapshot'=>'encrypted:array','expires_at'=>'datetime'];
    public $incrementing=false;
    protected $keyType='string';
}

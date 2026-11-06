<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreSellerAccount extends Model
{
    protected $table='store_seller_accounts';
    protected $guarded=[];
    protected $casts=['balance_minor'=>'integer','prepaid_minor'=>'integer','pending_minor'=>'integer','payout_held_minor'=>'integer'];
}

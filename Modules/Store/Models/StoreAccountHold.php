<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreAccountHold extends Model
{
    protected $table='store_account_holds';
    protected $guarded=[];
    protected $casts=['balance_minor'=>'integer','prepaid_minor'=>'integer','credit_minor'=>'integer'];
}

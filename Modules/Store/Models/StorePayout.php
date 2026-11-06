<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StorePayout extends Model
{
    protected $table='store_payouts';
    protected $guarded=[];
    protected $casts=['destination_snapshot'=>'encrypted:array','amount_minor'=>'integer','paid_at'=>'datetime'];
    protected $hidden=['credentials','integration_snapshot','payout_destination','destination_snapshot','payload'];
}

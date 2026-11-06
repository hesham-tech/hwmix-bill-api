<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreOutbox extends Model
{
    protected $table='store_outbox';
    protected $guarded=[];
    protected $casts=['payload'=>'encrypted:array','available_at'=>'datetime','locked_until'=>'datetime','attempts'=>'integer'];
    protected $hidden=['credentials','integration_snapshot','payout_destination','destination_snapshot','payload'];
}

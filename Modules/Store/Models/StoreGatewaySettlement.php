<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreGatewaySettlement extends Model {
    protected $guarded=[];
    protected $casts=['reference'=>'encrypted','gross_minor'=>'integer','net_minor'=>'integer','fee_minor'=>'integer','recoverable_tax_minor'=>'integer'];
}

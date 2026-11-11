<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreSellerProfile extends Model
{
    protected $table='store_seller_profiles';
    protected $guarded=[];
    protected $casts=['business_profile'=>'array','tax_profile'=>'array','pickup_address'=>'array','policy_overrides'=>'array','payout_destination'=>'encrypted:array','is_enabled'=>'boolean','policy_version'=>'integer','policy_review_required'=>'boolean'];
    protected $hidden=['credentials','integration_snapshot','payout_destination','destination_snapshot','payload'];
}

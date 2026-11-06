<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreEmailVerification extends Model {
    protected $guarded=[];
    protected $hidden=['token_hash'];
    protected $casts=['email'=>'encrypted','expires_at'=>'datetime','sent_at'=>'datetime','verified_at'=>'datetime','attempts'=>'integer'];
}

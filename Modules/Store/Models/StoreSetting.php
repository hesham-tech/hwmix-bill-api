<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreSetting extends Model
{
    protected $table='store_settings';
    protected $guarded=[];
    protected $casts=['value'=>'array'];
}

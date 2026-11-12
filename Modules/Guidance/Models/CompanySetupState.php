<?php
namespace Modules\Guidance\Models;
use Illuminate\Database\Eloquent\Model;
class CompanySetupState extends Model {
    protected $guarded=[];
    protected $casts=['reviewed'=>'array','deferred'=>'array','hidden'=>'boolean','finished_at'=>'datetime'];
}

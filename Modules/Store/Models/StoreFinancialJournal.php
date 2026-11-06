<?php
namespace Modules\Store\Models;
use Illuminate\Database\Eloquent\Model;
class StoreFinancialJournal extends Model {
    protected $table='store_financial_journals';
    public $timestamps=false;
    protected $guarded=[];
    protected $casts=['metadata'=>'array','created_at'=>'datetime'];
}

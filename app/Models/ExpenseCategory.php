<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'company_id',
        'created_by',
        'updated_by'
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function logLabel()
    {
        return "تصنيف مصروفات ({$this->name})";
    }
}

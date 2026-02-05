<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 */
class InvoiceType extends Model
{
    use HasFactory, Scopes, Blameable;
    protected $fillable = [
        'name',
        'description',
        'code',
        'context',
        'company_id',
        'created_by'
    ];
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * العلاقة مع الشركات عبر جدول الربط company_invoice_type
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_invoice_type')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}

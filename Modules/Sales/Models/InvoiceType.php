<?php

namespace Modules\Sales\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\User;

use App\Traits\LogsActivity;

/**
 * تعليق عربي: كلاس يمثل أنواع الفواتير المختلفة المتاحة في النظام داخل موديول المبيعات.
 */
class InvoiceType extends Model
{
    use HasFactory, Scopes, Blameable, LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "نوع الفاتورة ({$this->name})";
    }

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

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_invoice_type')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}

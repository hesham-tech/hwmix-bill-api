<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

/**
 * تعليق عربي: كلاس يمثل عروض الأسعار المقدمة للعملاء وقيمها وحالتها الحالية.
 */
class Quotation extends Model
{
    use Scopes, Blameable, LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "عرض سعر رقم #{$this->invoice_number} (مبلغ: {$this->total_amount})";
    }
    protected $fillable = [
        'invoice_number',
        'total_amount',
        'status',
        'company_id',
        'created_by',
    ];
}

<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Model;

use App\Traits\LogsActivity;

/**
 * تعليق عربي: كلاس يمثل الطلبات (أوامر البيع والشراء) في النظام وقيمها وحالتها الحالية.
 */
class Order extends Model
{
    use Scopes, Blameable, LogsActivity;

    /**
     * Label for activity logs.
     */
    public function logLabel()
    {
        return "طلب رقم #{$this->invoice_number} (مبلغ: {$this->total_amount})";
    }
    protected $fillable = [
        'invoice_number',
        'total_amount',
        'status',
        'company_id',
        'created_by',
    ];
}

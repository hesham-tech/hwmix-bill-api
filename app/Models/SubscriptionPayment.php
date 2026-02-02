<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    use HasFactory, Scopes, Blameable;

    protected $fillable = [
        'subscription_id',
        'company_id',
        'user_id',
        'created_by',
        'amount',
        'partial_payment_before',
        'partial_payment_after',
        'payment_date',
        'payment_method_id',
        'cash_box_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'partial_payment_before' => 'decimal:2',
        'partial_payment_after' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class);
    }
}

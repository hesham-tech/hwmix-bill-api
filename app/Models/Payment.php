<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperPayment
 */
class Payment extends Model
{
    use HasFactory, Blameable, Scopes;
    protected $fillable = [
        'user_id',
        'company_id',
        'created_by',
        'payment_date',
        'amount',
        'method',
        'notes',
        'is_split',
        'payment_method_id',
        'cash_box_id'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function installments()
    {
        return $this->belongsToMany(Installment::class, 'payment_installment')
            ->withPivot('allocated_amount')->withTimestamps();
    }
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

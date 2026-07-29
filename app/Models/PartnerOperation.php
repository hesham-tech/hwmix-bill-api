<?php

namespace App\Models;

use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * نموذج يمثل السجل التجاري والمالي لعمليات الشركاء
 */
class PartnerOperation extends Model
{
    use HasFactory, LogsActivity, Blameable, Scopes, SoftDeletes, FilterableByCompany;

    protected $fillable = [
        'company_id',
        'partner_id',
        'cashbox_id',
        'transaction_id',
        'type',
        'amount',
        'operation_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'operation_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($operation) {
            $operation->company_id = $operation->company_id ?? auth()->user()->active_company_id ?? null;
        });
    }

    /**
     * الشركة التابع لها السجل
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * الشريك صاحب العملية
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * الخزنة المتأثرة بالحركة النقدية
     */
    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class, 'cashbox_id');
    }

    /**
     * المعاملة النقدية المسجلة في النقدية
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * قيود دفتر الأستاذ المرتبطة بهذه العملية
     */
    public function financialLedgers(): MorphMany
    {
        return $this->morphMany(FinancialLedger::class, 'source');
    }

    /**
     * المستخدم الذي أنشأ السجل
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المستخدم الذي حدث السجل
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

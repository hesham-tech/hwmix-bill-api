<?php

namespace Modules\Companies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Models\Transaction;

/**
 * يمثل أرصدة المعاملات المالية المجمعة للطرف حسب نوع العلاقة (الذمم المدينة، الدائنة، العهد، السلف، رأس المال).
 */
class StakeholderFinancialBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stakeholder_financial_balances';

    protected $fillable = [
        'company_id',
        'user_id',
        'relation_type',
        'balance',
        'last_transaction_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * العلاقة مع الشركة الأم.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * العلاقة مع المستخدم (الطرف).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع الحركة المالية الأخيرة التي أثرت على الرصيد.
     */
    public function lastTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'last_transaction_id');
    }

    /**
     * العلاقة مع منشئ السجل.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

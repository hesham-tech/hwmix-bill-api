<?php
// موديل يعبر عن مصادر الرسائل المعتمدة المسموح بمعالجتها في كاش هونكس HwnixCash.

namespace Modules\HwnixCash\Models;

use App\Models\Company;
use App\Models\User;
use App\Traits\Blameable;
use App\Traits\FilterableByCompany;
use App\Traits\LogsActivity;
use App\Traits\Scopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HwnixCash\Domain\Enums\WalletProvider;

class HwnixCashMessageSource extends Model
{
    use HasFactory, SoftDeletes, FilterableByCompany, Blameable, LogsActivity, Scopes;

    protected $table = 'hwnix_cash_message_sources';

    protected $guarded = ['id'];

    protected $casts = [
        'provider' => WalletProvider::class,
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logLabel(): string
    {
        return "مصدر رسائل معتمد ({$this->sender_identifier})";
    }
}

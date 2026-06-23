<?php
// كلاس يمثل معاملات وقواعد التحويل بين الوحدات المختلفة داخل نفس المجموعة
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Company;
use App\Models\User;
use App\Traits\Scopes;
use App\Traits\Blameable;
use App\Traits\LogsActivity;

class UnitConversion extends Model
{
    use Scopes, Blameable, LogsActivity, \App\Traits\FilterableByCompanyOrSystem;

    protected $fillable = [
        'unit_group_id',
        'from_unit_id',
        'to_unit_id',
        'factor',
        'reverse_factor',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'factor' => 'float',
        'reverse_factor' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class, 'unit_group_id');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logLabel()
    {
        return "تحويل وحدة ({$this->fromUnit?->name} إلى {$this->toUnit?->name})";
    }
}

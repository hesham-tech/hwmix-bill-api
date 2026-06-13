<?php
// كلاس يمثل مجموعات وحدات القياس لضمان عدم خلط أو تحويل وحدات من أنواع مختلفة
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Company;
use App\Models\User;
use App\Traits\Scopes;
use App\Traits\Blameable;

class UnitGroup extends Model
{
    use Scopes, Blameable, \App\Traits\FilterableByCompanyOrSystem;

    protected $fillable = [
        'name',
        'type', // weight, length, volume, area, count, custom
        'company_id',
        'created_by',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'unit_group_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'unit_group_id');
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
        return "مجموعة الوحدات ({$this->name})";
    }
}

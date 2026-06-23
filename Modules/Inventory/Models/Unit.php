<?php
// كلاس يمثل وحدات القياس الفردية وضوابط الكسور العشرية المرتبطة بها
namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Company;
use App\Models\User;
use App\Traits\Scopes;
use App\Traits\Blameable;
use App\Traits\LogsActivity;

class Unit extends Model
{
    use Scopes, Blameable, LogsActivity, \App\Traits\FilterableByCompanyOrSystem;

    protected $fillable = [
        'unit_group_id',
        'name',
        'code',
        'decimal_places',
        'is_active',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class, 'unit_group_id');
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
        return "وحدة القياس ({$this->name})";
    }
}

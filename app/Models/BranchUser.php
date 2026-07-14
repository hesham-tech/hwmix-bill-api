<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Accounting\Models\CashBox;
use Modules\Inventory\Models\Warehouse;

/**
 * يمثل علاقة الموظف بالفرع ويحتوي على الإعدادات التشغيلية الخاصة بعضوية المستخدم داخل هذا الفرع.
 */
class BranchUser extends Pivot
{
    protected $table = 'branch_user';

    protected $guarded = [];

    /**
     * الحصول على الخزنة الافتراضية للمستخدم في هذا الفرع
     */
    public function defaultCashBox()
    {
        return $this->belongsTo(CashBox::class, 'default_cash_box_id');
    }

    /**
     * الحصول على المستودع الافتراضي للمستخدم في هذا الفرع
     */
    public function defaultWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }
}

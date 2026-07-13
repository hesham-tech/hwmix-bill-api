<?php

namespace App\Listeners;

use App\Events\CashBoxCreated;
use App\Events\CashBoxArchived;
use App\Events\CashBoxActivated;
use App\Events\CashBoxDeactivated;
use App\Events\DefaultCashBoxChanged;
use App\Events\CashBoxAccessGranted;
use App\Events\CashBoxAccessRevoked;
use App\Jobs\LogActivityJob;

/**
 * مستمع أحداث الخزن لتسجيل التغييرات الإدارية والصلاحيات (Audit Trail Listener)
 */
class CashBoxActivityLogListener
{
    private function logActivity($action, $cashBox, $actor, $description, $oldValues = null, $newValues = null)
    {
        $userId = $actor?->id;
        $companyId = $cashBox->company_id ?? $actor?->active_company_id;
        $branchId = $cashBox->branch_id ?? $actor?->branch_id;

        LogActivityJob::dispatch([
            'action' => $action,
            'model' => get_class($cashBox),
            'row_id' => $cashBox->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $userId,
            'created_by' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'url' => request()->getRequestUri(),
            'description' => $description,
        ]);
    }

    public function onCreated(CashBoxCreated $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $description = "قام المستخدم {$actorName} بإنشاء الخزنة #{$event->cashBox->id} ({$event->cashBox->name})";
        $this->logActivity('انشاء', $event->cashBox, $event->actor, $description, null, $event->cashBox->getAttributes());
    }

    public function onArchived(CashBoxArchived $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $description = "قام المستخدم {$actorName} بأرشفة الخزنة #{$event->cashBox->id} ({$event->cashBox->name})";
        $this->logActivity('تعديل', $event->cashBox, $event->actor, $description, ['status' => 'active'], ['status' => 'archived']);
    }

    public function onActivated(CashBoxActivated $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $description = "قام المستخدم {$actorName} بتنشيط الخزنة #{$event->cashBox->id} ({$event->cashBox->name})";
        $this->logActivity('تعديل', $event->cashBox, $event->actor, $description, ['status' => 'inactive'], ['status' => 'active']);
    }

    public function onDeactivated(CashBoxDeactivated $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $description = "قام المستخدم {$actorName} بتعطيل الخزنة #{$event->cashBox->id} ({$event->cashBox->name})";
        $this->logActivity('تعديل', $event->cashBox, $event->actor, $description, ['status' => 'active'], ['status' => 'inactive']);
    }

    public function onDefaultChanged(DefaultCashBoxChanged $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $targetName = $event->user ? ($event->user->nickname ?? $event->user->name) : 'مجهول';
        $description = "قام المستخدم {$actorName} بتغيير الخزنة الافتراضية للمستخدم {$targetName}";
        
        $fakeCashBox = new \Modules\Accounting\Models\CashBox();
        $fakeCashBox->id = $event->newDefaultId ?? 0;
        
        $this->logActivity('تعديل_الافتراضية', $fakeCashBox, $event->actor, $description, ['default_cash_box_id' => $event->oldDefaultId], ['default_cash_box_id' => $event->newDefaultId]);
    }

    public function onAccessGranted(CashBoxAccessGranted $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $targetName = $event->user ? ($event->user->nickname ?? $event->user->name) : 'مجهول';
        $description = "قام المستخدم {$actorName} بمنح المستخدم {$targetName} صلاحية الوصول للخزنة المشتركة #{$event->cashBox->id} ({$event->cashBox->name})";
        $this->logActivity('منح_صلاحية', $event->cashBox, $event->actor, $description, null, ['user_id' => $event->user->id]);
    }

    public function onAccessRevoked(CashBoxAccessRevoked $event)
    {
        $actorName = $event->actor ? ($event->actor->nickname ?? $event->actor->name) : 'النظام';
        $targetName = $event->user ? ($event->user->nickname ?? $event->user->name) : 'مجهول';
        $description = "قام المستخدم {$actorName} بإلغاء صلاحية الوصول للمستخدم {$targetName} من الخزنة المشتركة #{$event->cashBox->id} ({$event->cashBox->name})";
        $this->logActivity('إلغاء_صلاحية', $event->cashBox, $event->actor, $description, ['user_id' => $event->user->id], null);
    }

    /**
     * تسجيل الأحداث التابعة للمستمع
     */
    public function subscribe($events)
    {
        return [
            CashBoxCreated::class => 'onCreated',
            CashBoxArchived::class => 'onArchived',
            CashBoxActivated::class => 'onActivated',
            CashBoxDeactivated::class => 'onDeactivated',
            DefaultCashBoxChanged::class => 'onDefaultChanged',
            CashBoxAccessGranted::class => 'onAccessGranted',
            CashBoxAccessRevoked::class => 'onAccessRevoked',
        ];
    }
}

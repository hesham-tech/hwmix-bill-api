<?php

namespace Modules\Core\Actions;

/**
 * الكلاس الأساسي للأكشنز (Actions)
 * يوفر وظائف مشتركة لجميع العمليات المنطقية في النظام.
 */
abstract class BaseAction
{
    /**
     * تنفيذ الأكشن
     */
    abstract public function handle(array $data = []);

    /**
     * دالة مساعدة للتحقق من الصلاحيات بشكل مركزي إذا لزم الأمر
     */
    protected function authorize(string $permission)
    {
        if (!auth()->user()->hasPermissionTo(perm_key($permission)) && !auth()->user()->hasPermissionTo(perm_key('admin.super'))) {
            throw new \Illuminate\Auth\Access\AuthorizationException("غير مصرح لك بالقيام بهذه العملية.");
        }
    }
}

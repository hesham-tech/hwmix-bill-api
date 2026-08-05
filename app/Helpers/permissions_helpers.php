<?php

if (!function_exists('perm_key')) {
    /**
     * Get the full permission key from the permissions registry.
     *
     * @param string $permissionKey  مثال: 'users.view_all'
     * @return string
     */
    function perm_key(string $permissionKey): string
    {
        // تقسيم المفتاح المدخل إلى كيان (entity) وفعل (action)
        list($entity, $action) = explode('.', $permissionKey, 2);  // تحديد 2 لضمان صحة التقسيم

        // جلب جميع الصلاحيات من ملف config/permissions_keys.php
        $permissions = config('permissions_keys');

        // التحقق مما إذا كان الكيان والفعل موجودين في مصفوفة الصلاحيات
        if (isset($permissions[$entity][$action]['key'])) {
            return $permissions[$entity][$action]['key'];
        }

        // التفتيش داخل مصفوفات المجموعات المجمعة بحثاً عن المفتاح المباشر
        if (is_array($permissions)) {
            foreach ($permissions as $group) {
                if (is_array($group)) {
                    foreach ($group as $item) {
                        if (is_array($item) && isset($item['key']) && $item['key'] === $permissionKey) {
                            return $item['key'];
                        }
                    }
                }
            }
        }

        return $permissionKey;
    }
}

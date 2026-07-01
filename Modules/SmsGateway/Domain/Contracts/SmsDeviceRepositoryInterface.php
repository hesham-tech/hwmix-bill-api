<?php
// واجهة مستودع البيانات (Repository) الخاص بأجهزة بوابة الرسائل.

namespace Modules\SmsGateway\Domain\Contracts;

use Modules\SmsGateway\Domain\Entities\Device;

interface SmsDeviceRepositoryInterface
{
    /**
     * البحث عن جهاز بواسطة معرفه الرقمي.
     */
    public function findById(int $id): ?Device;

    /**
     * البحث عن جهاز بواسطة معرف الـ UUID.
     */
    public function findByUuid(string $uuid): ?Device;

    /**
     * البحث عن جهاز بواسطة معرف الـ Android ID.
     */
    public function findByAndroidId(string $androidId): ?Device;

    /**
     * حفظ أو تحديث بيانات الجهاز.
     */
    public function save(Device $device): Device;

    /**
     * حذف جهاز (Soft Delete).
     */
    public function delete(int $id): bool;

    /**
     * الحصول على جميع الأجهزة التابعة لشركة محددة.
     */
    public function getCompanyDevices(int $companyId): array;
}

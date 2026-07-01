<?php
// خدمة إدارة دورة حياة البوابات والأجهزة والنبضات ومزامنة التراخيص.

namespace Modules\SmsGateway\Services;

use Modules\SmsGateway\Domain\Contracts\SmsDeviceRepositoryInterface;
use Modules\SmsGateway\Domain\Entities\Device;
use Modules\SmsGateway\Domain\Enums\DeviceStatus;
use Modules\SmsGateway\Domain\Enums\LineStatus;
use Modules\SmsGateway\Models\SmsDevice;
use Modules\SmsGateway\Models\SmsDeviceSetting;
use Modules\SmsGateway\Models\SmsDeviceHeartbeat;
use Modules\SmsGateway\Models\SmsLine;
use Illuminate\Support\Facades\DB;

class SmsGatewayService
{
    public function __construct(
        protected SmsDeviceRepositoryInterface $deviceRepo
    ) {}

    /**
     * تسجيل أو تحديث جهاز أندرويد.
     */
    public function registerDevice(array $data, int $companyId, int $userId): Device
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            // البحث عن الجهاز بالـ UUID أو الـ Android ID
            $existingDevice = $this->deviceRepo->findByUuid($data['uuid']) 
                ?? $this->deviceRepo->findByAndroidId($data['android_id']);

            $deviceId = $existingDevice?->id;

            $device = new Device(
                id: $deviceId,
                companyId: $companyId,
                createdBy: $existingDevice?->createdBy ?? $userId,
                androidId: $data['android_id'],
                uuid: $data['uuid'],
                deviceName: $data['device_name'],
                brand: $data['brand'],
                model: $data['model'],
                androidVersion: $data['android_version'],
                appVersion: $data['app_version'],
                capabilities: $data['capabilities'] ?? [],
                status: $existingDevice?->status ?? DeviceStatus::Active,
                lastSeenAt: now()
            );

            $savedDevice = $this->deviceRepo->save($device);

            // التحقق من وجود الإعدادات وإنشاؤها إذا لم تكن موجودة
            $settingModel = SmsDeviceSetting::firstOrCreate(
                ['sms_device_id' => $savedDevice->id],
                [
                    'configuration_version' => 1,
                    'polling_interval_seconds' => 60,
                    'max_retry_count' => 3,
                    'logging_level' => 'info',
                    'feature_flags' => [
                        'enable_incoming_sync' => true,
                        'enable_outgoing_processing' => true
                    ],
                    'sync_limits' => [
                        'daily_limit' => 500
                    ]
                ]
            );

            // إطلاق حدث التسجيل إذا كان جهازاً جديداً
            if (!$deviceId) {
                event(new \Modules\SmsGateway\Events\DeviceRegistered($savedDevice));
            }

            return $savedDevice;
        });
    }

    /**
     * مزامنة الشرائح النشطة على الهاتف.
     */
    public function syncSimLines(int $deviceId, array $sims, int $companyId, int $userId): void
    {
        DB::transaction(function () use ($deviceId, $sims, $companyId, $userId) {
            // تعطيل كافة الشرائح السابقة للجهاز مؤقتاً لتثبيت التغيير الجديد
            SmsLine::where('sms_device_id', $deviceId)->update(['status' => LineStatus::Disabled->value]);

            foreach ($sims as $sim) {
                $lineModel = SmsLine::updateOrCreate(
                    [
                        'sms_device_id' => $deviceId,
                        'subscription_id' => $sim['subscription_id'],
                    ],
                    [
                        'company_id' => $companyId,
                        'created_by' => $userId,
                        'slot_index' => $sim['slot_index'],
                        'carrier' => $sim['carrier'],
                        'mcc' => $sim['mcc'] ?? null,
                        'mnc' => $sim['mnc'] ?? null,
                        'phone_number' => $sim['phone_number'] ?? null,
                        'network_type' => $sim['network_type'] ?? null,
                        'signal_strength' => $sim['signal_strength'] ?? null,
                        'status' => LineStatus::Active->value,
                    ]
                );

                // إطلاق حدث إدخال شريحة جديدة
                if ($lineModel->wasRecentlyCreated) {
                    event(new \Modules\SmsGateway\Events\SimInserted($lineModel));
                }
            }
        });
    }

    /**
     * تسجيل نبضات تشغيل الهاتف وصحته.
     */
    public function recordHeartbeat(int $deviceId, array $stats): array
    {
        return DB::transaction(function () use ($deviceId, $stats) {
            // 1. تسجيل سجل النبضة التاريخي
            SmsDeviceHeartbeat::create([
                'sms_device_id' => $deviceId,
                'network_type' => $stats['network_type'] ?? null,
                'battery_level' => $stats['battery_level'] ?? 100,
                'is_internet_available' => $stats['is_internet_available'] ?? true,
                'free_memory_bytes' => $stats['free_memory_bytes'] ?? null,
                'free_storage_bytes' => $stats['free_storage_bytes'] ?? null,
                'app_version' => $stats['app_version'],
            ]);

            // 2. تحديث وقت آخر تواجد للجهاز
            $deviceModel = SmsDevice::findOrFail($deviceId);
            $deviceModel->update([
                'last_seen_at' => now(),
                'app_version' => $stats['app_version'],
            ]);

            // 3. جلب الإعدادات والتحقق من رقم الإصدار
            $settings = SmsDeviceSetting::where('sms_device_id', $deviceId)->first();
            
            // 4. تقييم إصدارات التطبيق وتحديث سياسة الأمان (Force Update)
            $updatePolicy = $this->evaluateAppVersionPolicy($stats['app_version']);

            return [
                'config' => $settings,
                'update_policy' => $updatePolicy
            ];
        });
    }

    /**
     * تقييم إصدار تطبيق الأندرويد لفرض أو اقتراح التحديثات.
     */
    private function evaluateAppVersionPolicy(string $currentVersion): array
    {
        // الإصدارات الافتراضية المحددة في النظام (مستقبلاً يتم استهلاكها من config أو DB)
        $minSupportedVersion = '1.0.0';
        $latestVersion = '1.2.0';

        $needsForceUpdate = version_compare($currentVersion, $minSupportedVersion, '<');
        $needsOptionalUpdate = version_compare($currentVersion, $latestVersion, '<');

        return [
            'force_update' => $needsForceUpdate,
            'optional_update' => $needsOptionalUpdate && !$needsForceUpdate,
            'latest_version' => $latestVersion,
            'download_url' => config('services.agent.download_url', 'https://example.com/agent-latest.apk'),
        ];
    }
}

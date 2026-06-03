<?php

namespace Modules\Payment\Actions;

// تعليق عربي: أكشن لحفظ أو تحديث إعدادات بوابة الدفع للشركة الحالية مع تشفير الحقول السرية.

use Modules\Core\Actions\BaseAction;
use Modules\Payment\DTOs\PaymentGatewayDTO;
use Modules\Payment\Models\PaymentGateway;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class SavePaymentGatewayAction extends BaseAction
{
    public function handle(array $data = []): PaymentGateway
    {
        $this->authorize('companies.update');

        /** @var PaymentGatewayDTO $dto */
        $dto = $data['dto'];
        $companyId = Auth::user()->active_company_id;
        $existingConfig = [];

        if ($gatewayId) {
            $gateway = PaymentGateway::findOrFail($gatewayId);
            $existingConfig = $gateway->config ?? [];
        }

        // تشفير الحقول السرية الحساسة قبل الحفظ لضمان الأمان وعدم تسريب المفاتيح
        $encryptedConfig = array_map(function($value) {
            if (is_string($value) && !empty($value)) {
                return Crypt::encryptString($value);
            }
            return $value;
        }, $dto->config);

        $finalConfig = array_merge($existingConfig, $encryptedConfig);

        $gatewayData = [
            'name' => $dto->name,
            'driver' => $dto->driver,
            'config' => $finalConfig,
            'is_active' => $dto->isActive,
            'is_test_mode' => $dto->isTestMode,
            'is_default' => $dto->isDefault,
            'company_id' => $companyId,
        ];

        return \Illuminate\Support\Facades\DB::transaction(function() use ($gatewayId, $gatewayData, $companyId, $dto) {
            if ($dto->isDefault) {
                PaymentGateway::where('company_id', $companyId)
                    ->when($gatewayId, function($q) use ($gatewayId) {
                        $q->where('id', '!=', $gatewayId);
                    })
                    ->update(['is_default' => false]);
            }

            if ($gatewayId) {
                $gateway = PaymentGateway::findOrFail($gatewayId);
                $gateway->update($gatewayData);
            } else {
                $gateway = PaymentGateway::create($gatewayData);
            }

            return $gateway;
        });
    }
}

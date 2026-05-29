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
        $gatewayId = $data['id'] ?? null;

        // تشفير الحقول السرية الحساسة قبل الحفظ لضمان الأمان وعدم تسريب المفاتيح
        $encryptedConfig = array_map(function($value) {
            if (is_string($value) && !empty($value)) {
                return Crypt::encryptString($value);
            }
            return $value;
        }, $dto->config);

        $gatewayData = [
            'name' => $dto->name,
            'driver' => $dto->driver,
            'config' => $encryptedConfig,
            'is_active' => $dto->isActive,
            'is_test_mode' => $dto->isTestMode,
            'company_id' => Auth::user()->active_company_id,
        ];

        if ($gatewayId) {
            $gateway = PaymentGateway::findOrFail($gatewayId);
            $gateway->update($gatewayData);
        } else {
            $gateway = PaymentGateway::create($gatewayData);
        }

        return $gateway;
    }
}

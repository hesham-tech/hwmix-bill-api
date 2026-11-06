<?php

namespace Modules\Payment\Actions;

//   أكشن لحفظ أو تحديث إعدادات بوابة الدفع للشركة الحالية مع تشفير الحقول السرية.

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
        $companyId = Auth::user()->active_company_id;
        $existingConfig = [];

        if ($gatewayId) {
            $gateway = PaymentGateway::findOrFail($gatewayId);
            if ((int) $gateway->company_id !== (int) $companyId) abort(404);
            if ($gateway->driver !== $dto->driver) throw \Illuminate\Validation\ValidationException::withMessages(['driver'=>'أضف حسابًا جديدًا لتغيير مزود الدفع.']);
            $existingConfig = $gateway->config ?? [];
        }

        // تشفير الحقول السرية الحساسة قبل الحفظ لضمان الأمان وعدم تسريب المفاتيح
        $definition = \Modules\Payment\Services\GatewayRegistry::definition($dto->driver);
        $incomingConfig = array_filter(array_intersect_key($dto->config, $definition['fields']), fn ($value) => $value !== null && $value !== '' && !(is_string($value) && str_contains($value, '****')));
        $encryptedConfig = array_map(function ($value) {
            if (is_string($value) && !empty($value)) {
                return Crypt::encryptString($value);
            }
            return $value;
        }, $incomingConfig);

        $finalConfig = array_merge($existingConfig, $encryptedConfig);
        $candidate = new PaymentGateway(['driver'=>$dto->driver,'config'=>$finalConfig,'is_test_mode'=>$dto->isTestMode]);
        if ($dto->isActive && !\Modules\Payment\Services\GatewayRegistry::ready($dto->driver, \Modules\Payment\Services\PaymentGatewayFactory::configuration($candidate))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['config'=>'أكمل بيانات الحساب وتوقيع الإشعارات قبل تفعيله.']);
        }

        $gatewayData = [
            'name' => $dto->name,
            'driver' => $dto->driver,
            'config' => $finalConfig,
            'is_active' => $dto->isActive,
            'is_test_mode' => $dto->isTestMode,
            'is_default' => $dto->isDefault,
            'company_id' => $companyId,
        ];

        return \Illuminate\Support\Facades\DB::transaction(function () use ($gatewayId, $gatewayData, $companyId, $dto) {
            if ($dto->isDefault) {
                PaymentGateway::where('company_id', $companyId)
                    ->when($gatewayId, function ($q) use ($gatewayId) {
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

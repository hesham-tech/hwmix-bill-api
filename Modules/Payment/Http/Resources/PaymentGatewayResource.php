<?php

namespace Modules\Payment\Http\Resources;

// تعليق عربي: مورد بيانات بوابة الدفع لتحويل الكائن إلى استجابة JSON مع إخفاء وتغطية المفاتيح السرية الحساسة.

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{
    public function toArray($request)
    {
        // فك تشفير الإعدادات وعرض حقول معينة بشكل مقنع (Masked) للحفاظ على الأمان
        $rawConfig = $this->config;
        $maskedConfig = [];
        
        if (is_array($rawConfig)) {
            foreach ($rawConfig as $key => $val) {
                try {
                    $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($val);
                } catch (\Exception $e) {
                    $decrypted = $val;
                }

                // إخفاء الحقول التي تحتوي على كلمات سر أو رموز سرية
                if (in_array(strtolower($key), ['secret_key', 'api_key', 'password', 'token', 'private_key'])) {
                    $length = strlen($decrypted);
                    $maskedConfig[$key] = $length > 8 
                        ? substr($decrypted, 0, 4) . '****************' . substr($decrypted, -4)
                        : '********';
                } else {
                    $maskedConfig[$key] = $decrypted;
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver,
            'config' => $maskedConfig,
            'is_active' => (bool) $this->is_active,
            'is_test_mode' => (bool) $this->is_test_mode,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

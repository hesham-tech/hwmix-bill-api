<?php
namespace Modules\Payment\Services;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Services\Gateways\PaymobGateway;
use Modules\Payment\Services\Gateways\StripeGateway;
use InvalidArgumentException;
final class GatewayRegistry {
    public static function definitions(): array {
        return [
            'kashier'=>['name'=>'Kashier','class'=>\Modules\Payment\Services\Gateways\KashierGateway::class,
                'checkout_hosts'=>['payments.kashier.io'],'webhook_handler'=>\Modules\Payment\Services\KashierWebhookHandler::class,
                'fields'=>['api_key'=>'secret','secret_key'=>'secret','merchant_id'=>'text'],'required'=>['api_key','secret_key','merchant_id'],'capabilities'=>['cards','query','refunds']],
            'paymob'=>['name'=>'Paymob','class'=>PaymobGateway::class,'checkout_hosts'=>['accept.paymob.com'],
                'fields'=>['api_key'=>'secret','hmac_secret'=>'secret','integration_id'=>'text','iframe_id'=>'text'],
                'required'=>['api_key','hmac_secret','integration_id','iframe_id'],'capabilities'=>['cards','query','refunds']],
            'stripe'=>['name'=>'Stripe','class'=>StripeGateway::class,'checkout_hosts'=>['checkout.stripe.com'],
                'fields'=>['secret_key'=>'secret','webhook_secret'=>'secret','publishable_key'=>'text'],
                'required'=>['secret_key','webhook_secret'],'capabilities'=>['cards','query','refunds']],
        ];
    }
    public static function definition(string $driver): array {
        return self::definitions()[$driver] ?? throw new InvalidArgumentException('مزود الدفع غير مدعوم.');
    }
    public static function make(string $driver,array $config): PaymentGatewayInterface {
        $class=self::definition($driver)['class'];
        return new $class($config);
    }
    public static function ready(string $driver,array $config): bool {
        foreach(self::definition($driver)['required'] as $key) {
            if(!is_string($config[$key]??null)||trim($config[$key])==='') return false;
        }
        return true;
    }
}

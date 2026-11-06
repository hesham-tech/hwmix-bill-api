<?php
namespace Modules\Store\Services\Shipping;
use Modules\Store\Contracts\ShippingGateway;
final class ShippingGatewayRegistry {
    public static function definitions(): array {
        return ['manual'=>['name'=>'شركة شحن بإدارة يدوية','fields'=>[],'automatic'=>false,
            'class'=>ManualShippingGateway::class]];
    }
    public static function make(array $snapshot): ShippingGateway {
        $definition=self::definitions()[$snapshot['driver']??'']??null;
        if(!$definition) throw new \InvalidArgumentException('موصل الشحن غير مدعوم.');
        $class=$definition['class'];
        return new $class($snapshot);
    }
}

<?php
namespace Modules\Store\Services;
final class StoreSnapshot {
    private static function normalize(mixed $value): mixed {
        if(!is_array($value))return $value;
        if(!array_is_list($value))ksort($value);
        return array_map(self::normalize(...),$value);
    }
    public static function same(mixed $a,mixed $b): bool {
        return json_encode(self::normalize($a),JSON_THROW_ON_ERROR)===json_encode(self::normalize($b),JSON_THROW_ON_ERROR);
    }
}

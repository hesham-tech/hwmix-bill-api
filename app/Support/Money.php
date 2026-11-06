<?php

namespace App\Support;

use InvalidArgumentException;

/** Monetary arithmetic at the boundary: never round or compare money using floats. */
final class Money
{
    public static function minor(string|int|float $amount, int $scale = 2): int
    {
        if ($scale < 0 || $scale > 3) {
            throw new InvalidArgumentException('Unsupported monetary precision.');
        }
        $value = is_float($amount) ? sprintf('%.8F', $amount) : (string) $amount;
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $value, $parts)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }
        $fraction = $parts[3] ?? '';
        if (trim(substr($fraction, $scale), '0') !== '') {
            throw new InvalidArgumentException('The amount exceeds the currency precision.');
        }
        $digits = ltrim($parts[2] . str_pad(substr($fraction, 0, $scale), $scale, '0'), '0') ?: '0';
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new InvalidArgumentException('Monetary amount is too large.');
        }
        return ($parts[1] === '-' ? -1 : 1) * (int) $digits;
    }

    public static function decimal(int $minor, int $scale = 2): string
    {
        if ($minor === PHP_INT_MIN || $scale < 0 || $scale > 3) {
            throw new InvalidArgumentException('Invalid monetary amount or precision.');
        }
        $digits = str_pad((string) abs($minor), $scale + 1, '0', STR_PAD_LEFT);
        return ($minor < 0 ? '-' : '') . ($scale ? substr($digits, 0, -$scale) . '.' . substr($digits, -$scale) : $digits);
    }
}

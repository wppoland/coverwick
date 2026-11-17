<?php

declare(strict_types=1);

namespace Coverage\Support;

defined('ABSPATH') || exit;

/**
 * Number formatting with the store's WooCommerce separators.
 */
final class Format
{
    /**
     * Area with two decimals, e.g. "7,60".
     */
    public static function area(float $value): string
    {
        return number_format($value, 2, wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
    }

    /**
     * Coverage per unit with up to four decimals and no trailing zeros, e.g. "0,76".
     */
    public static function perUnit(float $value): string
    {
        $plain = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return str_replace('.', wc_get_price_decimal_separator(), $plain);
    }
}

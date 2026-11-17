<?php

declare(strict_types=1);

namespace Coverage\Support;

defined('ABSPATH') || exit;

/**
 * Pure area math, free of WordPress calls so it can be unit tested.
 *
 * Divisions are rounded to PRECISION decimals before ceil(): 7.6 / 0.76 is
 * 10.000000000000002 in floating point and must stay 10 pieces, not 11.
 */
final class Calculator
{
    public const PRECISION = 6;

    /**
     * Whole pieces needed to cover an area, rounded up to a full piece.
     */
    public static function piecesFor(float $area, float $perUnit): int
    {
        if ($area <= 0 || $perUnit <= 0) {
            return 0;
        }

        return (int) ceil(round($area / $perUnit, self::PRECISION));
    }

    /**
     * Area covered by a number of pieces.
     */
    public static function areaFor(int $pieces, float $perUnit): float
    {
        if ($pieces <= 0 || $perUnit <= 0) {
            return 0.0;
        }

        return round($pieces * $perUnit, self::PRECISION);
    }

    /**
     * Price of one area unit, or null when the product has no coverage or price.
     */
    public static function pricePerArea(float $price, float $perUnit): ?float
    {
        if ($perUnit <= 0 || $price < 0) {
            return null;
        }

        return round($price / $perUnit, self::PRECISION);
    }

    /**
     * A variation's own coverage wins; otherwise the parent's value applies.
     */
    public static function resolvePerUnit(float $own, float $parent): float
    {
        if ($own > 0) {
            return $own;
        }

        return $parent > 0 ? $parent : 0.0;
    }

    /**
     * Lowest non-null value, or null when there is none.
     *
     * @param array<int|string, float|null> $values
     */
    public static function lowest(array $values): ?float
    {
        $values = array_filter($values, static fn (?float $v): bool => $v !== null);

        return $values === [] ? null : (float) min($values);
    }

    /**
     * Parse a shopper or admin number: accepts "0,76", "0.76" and "1 234,5".
     */
    public static function parseDecimal(string $raw): float
    {
        $raw = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $raw) ?? '';
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            // The last separator is the decimal one.
            $raw = strrpos($raw, ',') > strrpos($raw, '.')
                ? str_replace(['.', ','], ['', '.'], $raw)
                : str_replace(',', '', $raw);
        } else {
            $raw = str_replace(',', '.', $raw);
        }

        return is_numeric($raw) ? max(0.0, (float) $raw) : 0.0;
    }

    /**
     * Render a typed unit: a trailing 2 or 3 becomes a superscript ("m2" is "m²").
     */
    public static function displayUnit(string $unit): string
    {
        $unit = trim($unit);
        if (preg_match('/^(.*\D)([23])$/', $unit, $m) === 1) {
            return $m[1] . ($m[2] === '2' ? "\u{00B2}" : "\u{00B3}");
        }

        return $unit;
    }
}

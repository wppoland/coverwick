<?php

declare(strict_types=1);

namespace Coverage\Product;

use Coverage\Support\Calculator;

defined('ABSPATH') || exit;

/**
 * Coverage data of a product: area per sellable unit and price per area unit.
 */
final class Area
{
    public const META_PER_UNIT     = '_coverage_m2_per_unit';
    public const META_PRICE_PER_M2 = '_coverage_price_per_m2';
    public const META_SAMPLE       = '_coverage_sample_enabled';
    public const META_SAMPLE_PRICE = '_coverage_sample_price';

    /**
     * Area covered by one unit. A variation without its own value inherits the parent's.
     */
    public static function perUnit(\WC_Product $product): float
    {
        $own = self::ownPerUnit($product);
        if ($own > 0 || $product->get_parent_id() <= 0) {
            return $own;
        }

        $parent = wc_get_product($product->get_parent_id());

        return Calculator::resolvePerUnit($own, $parent instanceof \WC_Product ? self::ownPerUnit($parent) : 0.0);
    }

    public static function ownPerUnit(\WC_Product $product): float
    {
        return max(0.0, (float) $product->get_meta(self::META_PER_UNIT, true));
    }

    /**
     * True when the product or any of its variations has a coverage value.
     */
    public static function has(\WC_Product $product): bool
    {
        if (self::ownPerUnit($product) > 0) {
            return true;
        }
        foreach (self::variations($product) as $variation) {
            if (self::ownPerUnit($variation) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowest price per area unit. Simple: price / coverage. Variable: the minimum
     * over each visible variation's own price and coverage (parent fallback).
     *
     * @param (callable(\WC_Product): (float|null))|null $priceOf Price source, defaults to the stored active price.
     */
    public static function lowestPricePerArea(\WC_Product $product, ?callable $priceOf = null): ?float
    {
        $priceOf ??= static fn (\WC_Product $p): ?float => $p->get_price() === '' ? null : (float) $p->get_price();

        $candidates = $product->is_type('variable') ? self::variations($product) : [$product];
        $values     = [];
        foreach ($candidates as $candidate) {
            $price = $priceOf($candidate);
            if ($price === null) {
                continue;
            }
            $values[] = Calculator::pricePerArea($price, self::perUnit($candidate));
        }

        return Calculator::lowest($values);
    }

    /**
     * @return list<\WC_Product>
     */
    public static function variations(\WC_Product $product): array
    {
        if (! $product->is_type('variable')) {
            return [];
        }
        $out = [];
        foreach ($product->get_children() as $childId) {
            $child = wc_get_product($childId);
            if ($child instanceof \WC_Product && $child->get_status() === 'publish') {
                $out[] = $child;
            }
        }

        return $out;
    }
}

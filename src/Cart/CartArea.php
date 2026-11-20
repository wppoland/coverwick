<?php

declare(strict_types=1);

namespace Coverage\Cart;

use Coverage\Contract\HasHooks;
use Coverage\Product\Area;
use Coverage\Settings;
use Coverage\Support\Calculator;
use Coverage\Support\Format;

defined('ABSPATH') || exit;

/**
 * Carries the coverage per unit on cart lines, shows the covered area in the
 * cart and checkout (classic and Store API) and saves it on order items.
 *
 * Only the per-unit value is stored on the line: the area is always derived
 * from the current quantity, so a quantity change in the cart cannot leave a
 * stale area behind and identical products still merge into one line.
 */
final class CartArea implements HasHooks
{
    public const CART_KEY       = 'coverage_per_unit';
    public const ORDER_META     = '_coverage_m2';
    public const ORDER_META_PER = '_coverage_m2_per_unit';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        add_filter('woocommerce_add_cart_item_data', [$this, 'addCartItemData'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'itemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveOrderItem'], 10, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'formattedMeta'], 10, 2);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hiddenMeta']);
    }

    /**
     * The order screen lists underscore meta too: keep the raw helper values
     * out. `_coverage_m2` stays, formattedMeta() turns its row into "Area".
     *
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    public function hiddenMeta(array $keys): array
    {
        return array_merge($keys, [self::ORDER_META_PER, '_coverage_sample_source', '_coverage_sample_variation']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addCartItemData(array $data, int $productId, int $variationId): array
    {
        $product = wc_get_product($variationId > 0 ? $variationId : $productId);
        if ($product instanceof \WC_Product) {
            $perUnit = Area::perUnit($product);
            if ($perUnit > 0) {
                $data[self::CART_KEY] = $perUnit;
            }
        }

        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $itemData
     * @param array<string, mixed>             $cartItem
     * @return array<int, array<string, mixed>>
     */
    public function itemData(array $itemData, array $cartItem): array
    {
        $area = self::areaOf($cartItem);
        if ($area > 0) {
            $itemData[] = [
                'key'   => __('Area', 'coverwick'),
                'value' => Format::area($area) . ' ' . $this->settings->unit(),
            ];
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveOrderItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values): void
    {
        $area = self::areaOf($values);
        if ($area > 0) {
            $item->add_meta_data(self::ORDER_META, wc_format_decimal($area, Calculator::PRECISION, true), true);
            $item->add_meta_data(self::ORDER_META_PER, wc_format_decimal((float) $values[self::CART_KEY], Calculator::PRECISION, true), true);
        }
    }

    /**
     * Hidden meta is skipped by WooCommerce; add a readable "Area" row for
     * emails, the thank-you page, My Account and the order screen. The row is
     * keyed by the real meta id, so on the order screen it replaces the raw
     * row and saving the order writes the same `_coverage_m2` value back.
     *
     * @param array<int|string, object> $formatted
     * @return array<int|string, object>
     */
    public function formattedMeta(array $formatted, \WC_Order_Item $item): array
    {
        $area = (float) $item->get_meta(self::ORDER_META, true);
        if ($area > 0) {
            $rowId = 'coverage_area';
            foreach ($item->get_meta_data() as $meta) {
                if ($meta->key === self::ORDER_META && $meta->id) {
                    $rowId = $meta->id;
                }
            }
            $formatted[$rowId] = (object) [
                'key'           => self::ORDER_META,
                'value'         => (string) $item->get_meta(self::ORDER_META, true),
                'display_key'   => __('Area', 'coverwick'),
                'display_value' => esc_html(Format::area($area) . ' ' . $this->settings->unit()),
            ];
        }

        return $formatted;
    }

    /**
     * @param array<string, mixed> $cartItem
     */
    public static function areaOf(array $cartItem): float
    {
        $perUnit = (float) ($cartItem[self::CART_KEY] ?? 0);

        return Calculator::areaFor((int) ($cartItem['quantity'] ?? 0), $perUnit);
    }
}

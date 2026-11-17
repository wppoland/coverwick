<?php

declare(strict_types=1);

namespace Coverage\Admin;

use Coverage\Contract\HasHooks;
use Coverage\Product\Area;
use Coverage\Settings;
use Coverage\Support\Calculator;

defined('ABSPATH') || exit;

/**
 * Product edit fields: coverage per unit (General tab and per variation) and
 * the paid sample toggle with an optional price override.
 *
 * Nonces are verified by WooCommerce before the process hooks used here fire
 * (woocommerce_meta_nonce for the product, save-variations for variations).
 */
final class ProductFields implements HasHooks
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderGeneral']);
        add_action('woocommerce_admin_process_product_object', [$this, 'saveGeneral']);
        add_action('woocommerce_variation_options_pricing', [$this, 'renderVariation'], 20, 3);
        add_action('woocommerce_admin_process_variation_object', [$this, 'saveVariation'], 10, 2);
    }

    public function renderGeneral(): void
    {
        echo '<div class="options_group coverage-product-fields">';

        woocommerce_wp_text_input([
            'id'          => Area::META_PER_UNIT,
            /* translators: %s: area unit, e.g. m² */
            'label'       => sprintf(__('Coverage per unit (%s)', 'coverage'), $this->settings->unit()),
            'description' => __('Area one sellable unit covers, e.g. 0.76 for a board. Variations without their own value use this one. Leave empty to turn the calculator off.', 'coverage'),
            'desc_tip'    => true,
            'data_type'   => 'decimal',
        ]);

        woocommerce_wp_checkbox([
            'id'          => Area::META_SAMPLE,
            'label'       => __('Sample available', 'coverage'),
            'description' => __('Show an "Order a sample" button on the product page.', 'coverage'),
        ]);

        woocommerce_wp_text_input([
            'id'          => Area::META_SAMPLE_PRICE,
            'label'       => sprintf(
                /* translators: %s: currency symbol */
                __('Sample price (%s)', 'coverage'),
                get_woocommerce_currency_symbol(),
            ),
            'placeholder' => wc_format_localized_price((string) $this->settings->samplePrice()),
            'description' => __('Leave empty to use the default sample price from the plugin settings.', 'coverage'),
            'desc_tip'    => true,
            'data_type'   => 'price',
        ]);

        echo '</div>';
    }

    public function saveGeneral(\WC_Product $product): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (woocommerce_meta_nonce).
        $perUnit = isset($_POST[Area::META_PER_UNIT]) ? wc_clean(wp_unslash((string) $_POST[Area::META_PER_UNIT])) : '';
        $price   = isset($_POST[Area::META_SAMPLE_PRICE]) ? wc_clean(wp_unslash((string) $_POST[Area::META_SAMPLE_PRICE])) : '';
        $sample  = isset($_POST[Area::META_SAMPLE]);
        // phpcs:enable

        $this->setPerUnit($product, is_string($perUnit) ? $perUnit : '');
        $product->update_meta_data(Area::META_SAMPLE, $sample ? 'yes' : 'no');

        $price = is_string($price) ? trim($price) : '';
        if ($price === '') {
            $product->delete_meta_data(Area::META_SAMPLE_PRICE);
        } else {
            $product->update_meta_data(Area::META_SAMPLE_PRICE, wc_format_decimal(Calculator::parseDecimal($price)));
        }
    }

    /**
     * @param array<string, mixed> $variationData
     */
    public function renderVariation(int $loop, array $variationData, \WP_Post $variation): void
    {
        $value = get_post_meta($variation->ID, Area::META_PER_UNIT, true);

        woocommerce_wp_text_input([
            'id'            => 'coverage_per_unit_' . $loop,
            'name'          => 'coverage_per_unit[' . $loop . ']',
            'value'         => is_scalar($value) ? wc_format_localized_decimal((string) $value) : '',
            /* translators: %s: area unit, e.g. m² */
            'label'         => sprintf(__('Coverage per unit (%s)', 'coverage'), $this->settings->unit()),
            'placeholder'   => __('Same as parent', 'coverage'),
            'desc_tip'      => true,
            'description'   => __('Area one unit of this variation covers. Empty uses the value from the General tab.', 'coverage'),
            'data_type'     => 'decimal',
            'wrapper_class' => 'form-row form-row-full',
        ]);
    }

    public function saveVariation(\WC_Product_Variation $variation, int $index): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (save-variations).
        $raw = isset($_POST['coverage_per_unit'][$index]) ? wc_clean(wp_unslash((string) $_POST['coverage_per_unit'][$index])) : '';
        $this->setPerUnit($variation, is_string($raw) ? $raw : '');
    }

    private function setPerUnit(\WC_Product $product, string $raw): void
    {
        $value = Calculator::parseDecimal($raw);
        if ($value > 0) {
            $product->update_meta_data(Area::META_PER_UNIT, wc_format_decimal($value));
        } else {
            $product->delete_meta_data(Area::META_PER_UNIT);
        }
    }
}

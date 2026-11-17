<?php

declare(strict_types=1);

namespace Coverage\Frontend;

use Coverage\Contract\HasHooks;
use Coverage\Product\Area;
use Coverage\Settings;
use Coverage\Support\Format;

defined('ABSPATH') || exit;

/**
 * Single product page: the area input linked to the WooCommerce quantity
 * (which is the pieces input), the hint line and the price per area unit.
 *
 * JS contract for add-ons: the box dispatches a bubbling `coverage:change`
 * CustomEvent with detail { area, pieces, perUnit, price, productId, variationId }.
 */
final class ProductPage implements HasHooks
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('woocommerce_before_add_to_cart_quantity', [$this, 'renderCalculator']);
        add_filter('woocommerce_get_price_html', [$this, 'priceHtml'], 20, 2);
    }

    /**
     * Register on every product page (samples enqueue the same handle when they
     * render), enqueue right away when the product has coverage.
     */
    public function enqueue(): void
    {
        if (! is_product()) {
            return;
        }

        wp_register_style('coverage', \COVERAGE_URL . 'assets/css/coverage.css', [], \Coverage\VERSION);
        wp_register_script('coverage', \COVERAGE_URL . 'assets/js/coverage.js', [], \Coverage\VERSION, ['in_footer' => true, 'strategy' => 'defer']);
        wp_add_inline_script('coverage', 'window.coverageCalc = ' . wp_json_encode([
            'decimalSeparator'  => wc_get_price_decimal_separator(),
            'thousandSeparator' => wc_get_price_thousand_separator(),
            'priceDecimals'     => wc_get_price_decimals(),
            'currencySymbol'    => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES),
            'priceFormat'       => get_woocommerce_price_format(),
        ]) . ';', 'before');

        $product = wc_get_product(get_queried_object_id());
        if ($product instanceof \WC_Product && Area::has($product)) {
            wp_enqueue_style('coverage');
            wp_enqueue_script('coverage');
        }
    }

    public function renderCalculator(): void
    {
        global $product;
        if (! $product instanceof \WC_Product || ! Area::has($product)) {
            return;
        }

        $perUnit    = Area::ownPerUnit($product);
        $variations = [];
        foreach (Area::variations($product) as $variation) {
            $variations[$variation->get_id()] = [
                'u' => Area::perUnit($variation),
                'p' => $this->displayPrice($variation),
            ];
        }
        $id = 'coverage-area-' . $product->get_id();
        ?>
        <div class="coverage-calc"
            data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>"
            data-per-unit="<?php echo esc_attr((string) $perUnit); ?>"
            data-price="<?php echo esc_attr((string) ($this->displayPrice($product) ?? '')); ?>"
            data-variations="<?php echo esc_attr((string) wp_json_encode((object) $variations)); ?>"
            <?php echo $perUnit > 0 ? '' : 'hidden'; ?>>
            <label class="coverage-calc__label" for="<?php echo esc_attr($id); ?>"><?php esc_html_e('Area', 'coverage'); ?></label>
            <span class="coverage-calc__field">
                <input type="text" inputmode="decimal" autocomplete="off" class="input-text coverage-calc__area" id="<?php echo esc_attr($id); ?>" />
                <span class="coverage-calc__unit"><?php echo esc_html($this->settings->unit()); ?></span>
            </span>
            <p class="coverage-calc__hint">
                <?php
                printf(
                    /* translators: 1: area one piece covers, e.g. 0,76 2: area unit, e.g. m2 */
                    esc_html__('1 pc = %1$s %2$s. Enter the number of pieces or the area, the other value is calculated. Area is rounded up to a whole piece.', 'coverage'),
                    '<span class="coverage-calc__per-unit">' . esc_html(Format::perUnit($perUnit)) . '</span>',
                    esc_html($this->settings->unit()),
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function priceHtml(string $html, \WC_Product $product): string
    {
        if (! is_product() || $html === '') {
            return $html;
        }
        $queried = get_queried_object_id();
        if ($product->get_id() !== $queried && $product->get_parent_id() !== $queried) {
            return $html;
        }

        $value = Area::lowestPricePerArea($product, fn (\WC_Product $p): ?float => $this->displayPrice($p));
        if ($value === null) {
            return $html;
        }

        $label = $product->is_type('variable')
            /* translators: 1: formatted price, 2: area unit, e.g. m2 */
            ? __('from %1$s / %2$s', 'coverage')
            /* translators: 1: formatted price, 2: area unit, e.g. m2 */
            : __('%1$s / %2$s', 'coverage');

        return $html . ' <span class="coverage-unit-price">' . sprintf(esc_html($label), wc_price($value), esc_html($this->settings->unit())) . '</span>';
    }

    private function displayPrice(\WC_Product $product): ?float
    {
        if ($product->get_price() === '') {
            return null;
        }

        return (float) wc_get_price_to_display($product);
    }
}

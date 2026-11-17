<?php
/**
 * Integration check for price per m2 sync, cart area and samples. Creates its
 * own products, prints PASS/FAIL per check and exits 1 on any failure.
 *
 * Run inside a throwaway wp-env with WooCommerce and this plugin active:
 *   npx @wordpress/env run cli wp eval-file wp-content/plugins/coverage/tests/integration/cart-check.php
 *
 * @package Coverage
 */

use Coverage\Cart\CartArea;
use Coverage\Plugin;
use Coverage\Product\Area;
use Coverage\Sample\SampleProduct;
use Coverage\Sample\Samples;
use Coverage\Settings;

defined('ABSPATH') || exit;

$coverage_failures = 0;
$coverage_check    = static function (string $label, bool $ok, string $detail = '') use (&$coverage_failures): void {
    if (! $ok) {
        ++$coverage_failures;
    }
    WP_CLI::line(($ok ? 'PASS ' : 'FAIL ') . $label . ($detail !== '' ? ' (' . $detail . ')' : ''));
};
$coverage_meta = static fn (int $id): string => (string) get_post_meta($id, Area::META_PRICE_PER_M2, true);

$container = Plugin::instance()->container();
/** @var Settings $settings */
$settings = $container->get(Settings::class);
$coverage_class = wp_insert_term('Coverage check samples', 'product_shipping_class');
$coverage_class = is_wp_error($coverage_class) ? 0 : (int) $coverage_class['term_id'];
update_option(Settings::OPTION, ['unit' => 'm2', 'samples_enabled' => true, 'sample_price' => '4', 'sample_max' => 2, 'sample_shipping_class' => $coverage_class]);
$settings->flush();

// Simple board: 199 / 0.76 = 261.84.
$simple = new WC_Product_Simple();
$simple->set_name('Check board simple');
$simple->set_regular_price('199');
$simple->update_meta_data(Area::META_PER_UNIT, '0.76');
$simple->update_meta_data(Area::META_SAMPLE, 'yes');
$simple->save();
$coverage_check('simple price per m2 meta', $coverage_meta($simple->get_id()) === '261.84', $coverage_meta($simple->get_id()));

// Variable board: A has its own 0.5 at 150 (300/m2), B inherits 0.76 at 120 (157.89/m2).
$attribute = new WC_Product_Attribute();
$attribute->set_name('Colour');
$attribute->set_options(['Graphite', 'Teak']);
$attribute->set_variation(true);
$attribute->set_visible(true);
$variable = new WC_Product_Variable();
$variable->set_name('Check board variable');
$variable->set_attributes([$attribute]);
$variable->update_meta_data(Area::META_PER_UNIT, '0.76');
$variable->update_meta_data(Area::META_SAMPLE, 'yes');
$variable->save();

$varA = new WC_Product_Variation();
$varA->set_parent_id($variable->get_id());
$varA->set_attributes(['colour' => 'Graphite']);
$varA->set_regular_price('150');
$varA->update_meta_data(Area::META_PER_UNIT, '0.5');
$varA->save();
$varB = new WC_Product_Variation();
$varB->set_parent_id($variable->get_id());
$varB->set_attributes(['colour' => 'Teak']);
$varB->set_regular_price('120');
$varB->save();
WC_Product_Variable::sync($variable->get_id());
$coverage_check('variable lowest price per m2 with parent fallback', $coverage_meta($variable->get_id()) === '157.89', $coverage_meta($variable->get_id()));
$coverage_check('variation inherits parent coverage', Area::perUnit(wc_get_product($varB->get_id())) === 0.76);

// Price change on a variation updates the parent: B now 200/0.76 = 263.16, A 300.
$varB = wc_get_product($varB->get_id());
$varB->set_regular_price('200');
$varB->save();
$coverage_check('variation price change resyncs parent', $coverage_meta($variable->get_id()) === '263.16', $coverage_meta($variable->get_id()));

// Scheduled sale through the WooCommerce cron callback: A on sale at 100 = 200/m2.
$varA = wc_get_product($varA->get_id());
$varA->set_sale_price('100');
$varA->set_date_on_sale_from((string) (time() + DAY_IN_SECONDS));
$varA->save();
update_post_meta($varA->get_id(), '_sale_price_dates_from', time() - HOUR_IN_SECONDS);
wc_scheduled_sales();
$coverage_check('scheduled sale start resyncs parent', $coverage_meta($variable->get_id()) === '200.00', $coverage_meta($variable->get_id()));

// No coverage: meta removed.
$plain = new WC_Product_Simple();
$plain->set_name('Check plain');
$plain->set_regular_price('10');
$plain->update_meta_data(Area::META_PRICE_PER_M2, '1');
$plain->save();
$coverage_check('meta deleted without coverage', $coverage_meta($plain->get_id()) === '');

// Cart.
wc_load_cart();
WC()->cart->empty_cart();
$key  = WC()->cart->add_to_cart($simple->get_id(), 10);
$item = WC()->cart->get_cart_item((string) $key);
$coverage_check('cart line carries per unit', (float) ($item[CartArea::CART_KEY] ?? 0) === 0.76);
$coverage_check('cart area derived from quantity', CartArea::areaOf($item) === 7.6);
$data = apply_filters('woocommerce_get_item_data', [], $item);
$coverage_check('cart item data shows area', in_array(\Coverage\Support\Format::area(7.6) . " m\u{00B2}", array_column($data, 'value'), true), (string) wp_json_encode($data));
WC()->cart->set_quantity((string) $key, 12);
$coverage_check('area follows quantity change', CartArea::areaOf(WC()->cart->get_cart_item((string) $key)) === 9.12);

/** @var Samples $samples */
$samples = $container->get(Samples::class);
/** @var SampleProduct $sampleProduct */
$sampleProduct = $container->get(SampleProduct::class);

$coverage_check('sample of simple product added', $samples->add($simple->get_id()));
$coverage_check('same sample twice refused', ! $samples->add($simple->get_id()));
$coverage_check('variable sample without variation refused', ! $samples->add($variable->get_id()));
$coverage_check('variable sample with variation added', $samples->add($variable->get_id(), $varA->get_id()));
$coverage_check('sample limit enforced', ! $samples->add($variable->get_id(), $varB->get_id()));
$coverage_check('direct add of internal product refused', ! apply_filters('woocommerce_add_to_cart_validation', true, $sampleProduct->id(), 1));
wc_clear_notices();

$lines = array_values(array_filter(WC()->cart->get_cart(), static fn (array $i): bool => isset($i[Samples::CART_KEY])));
$coverage_check('two sample lines', count($lines) === 2);
$coverage_check('sample line name', $lines[0]['data']->get_name() === 'Sample: Check board simple', $lines[0]['data']->get_name());
$coverage_check('sample line price', (float) $lines[0]['data']->get_price() === 4.0);
$coverage_check('sample line sold individually', $lines[0]['data']->is_sold_individually() && $lines[0]['quantity'] === 1);
$coverage_check('sample line gets the sample shipping class', $coverage_class > 0 && $lines[0]['data']->get_shipping_class_id() === $coverage_class);
$coverage_check('sample line has no area', CartArea::areaOf($lines[0]) === 0.0);
$attrData = apply_filters('woocommerce_get_item_data', [], $lines[1]);
$coverage_check('variant sample shows attribute', in_array('Graphite', array_column($attrData, 'value'), true), (string) wp_json_encode($attrData));

// Per product price override.
$simple = wc_get_product($simple->get_id());
$simple->update_meta_data(Area::META_SAMPLE_PRICE, '6.5');
$simple->save();
WC()->cart->calculate_totals();
$lines = array_values(array_filter(WC()->cart->get_cart(), static fn (array $i): bool => isset($i[Samples::CART_KEY])));
$coverage_check('per product sample price override', (float) $lines[0]['data']->get_price() === 6.5, (string) $lines[0]['data']->get_price());

// Order items.
$order = wc_create_order();
WC()->checkout()->create_order_line_items($order, WC()->cart);
$order->save();
$byName = [];
foreach ($order->get_items() as $orderItem) {
    $byName[$orderItem->get_name()] = $orderItem;
}
$board = $byName['Check board simple'] ?? null;
$coverage_check('order item saves area meta', $board !== null && $board->get_meta(CartArea::ORDER_META) === '9.12', $board !== null ? (string) $board->get_meta(CartArea::ORDER_META) : '');
$formatted = $board !== null ? wp_list_pluck($board->get_formatted_meta_data(), 'display_key') : [];
$coverage_check('order item shows Area', in_array('Area', $formatted, true), (string) wp_json_encode($formatted));
$sampleItem = $byName['Sample: Check board simple'] ?? null;
$coverage_check('order sample item named and linked to source', $sampleItem !== null && (int) $sampleItem->get_meta('_coverage_sample_source') === $simple->get_id());
$coverage_check('order sample item does not touch source stock product', $sampleItem !== null && $sampleItem->get_product_id() === $sampleProduct->id());

// Trashed internal product is recreated on next use.
$oldId = $sampleProduct->id();
wp_trash_post($oldId);
$fresh = new SampleProduct($settings);
$coverage_check('trashed sample product recreated', $fresh->id() !== $oldId && $fresh->id() > 0);

// Cleanup.
WC()->cart->empty_cart();
$order->delete(true);
foreach ([$simple->get_id(), $variable->get_id(), $plain->get_id()] as $id) {
    $p = wc_get_product($id);
    if ($p instanceof WC_Product_Variable) {
        foreach ($p->get_children() as $child) {
            wp_delete_post($child, true);
        }
    }
    wp_delete_post($id, true);
}
wp_delete_post($oldId, true);
wp_delete_term($coverage_class, 'product_shipping_class');

WP_CLI::line($coverage_failures === 0 ? 'ALL PASS' : $coverage_failures . ' FAILED');
if ($coverage_failures > 0) {
    exit(1);
}

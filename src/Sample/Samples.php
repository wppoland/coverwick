<?php

declare(strict_types=1);

namespace Coverage\Sample;

use Coverage\Contract\HasHooks;
use Coverage\Product\Area;
use Coverage\Settings;

defined('ABSPATH') || exit;

/**
 * Paid samples as separate cart lines.
 *
 * Every sample line is the hidden SampleProduct carrying the source product
 * (and variation) as cart item data. The data makes one line per source, the
 * internal product keeps stock, sales reports and stock reduction of the real
 * product untouched, and per-line name, price, image and shipping class are
 * set on the line's own product object, which the Store API (Blocks) and the
 * classic templates both read.
 */
final class Samples implements HasHooks
{
    public const CART_KEY = 'coverage_sample';

    private bool $adding = false;

    public function __construct(
        private readonly Settings $settings,
        private readonly SampleProduct $sampleProduct,
    ) {
    }

    public function registerHooks(): void
    {
        // After each product type's add-to-cart template, outside its form: the
        // action fires for out-of-stock products too (samples do not depend on
        // stock) and in block and page-builder templates that render that form.
        foreach (array_keys(wc_get_product_types()) as $type) {
            add_action('woocommerce_' . $type . '_add_to_cart', [$this, 'renderButton'], 35);
        }
        add_action('wp_loaded', [$this, 'handleForm'], 20);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'blockDirectAdd'], 1, 2);
        add_filter('woocommerce_add_cart_item', [$this, 'applyToLine'], 20);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'applyToLine'], 20);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'dropInvalidLines']);
        add_action('woocommerce_before_calculate_totals', [$this, 'reapplyPrices'], 20);
        add_filter('woocommerce_get_item_data', [$this, 'itemData'], 10, 2);
        add_filter('woocommerce_cart_item_permalink', [$this, 'permalink'], 10, 2);
        add_filter('woocommerce_store_api_product_quantity_editable', [$this, 'quantityEditable'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveOrderItem'], 10, 3);
        add_action('woocommerce_check_cart_items', [$this, 'checkLimit']);
    }

    /**
     * Samples are on globally and the published (parent) product has them switched on.
     */
    public function available(\WC_Product $product): bool
    {
        $source = $this->sourceOf($product);

        return $source !== null
            && $this->settings->samplesEnabled()
            && $source->get_status() === 'publish'
            && $source->get_meta(Area::META_SAMPLE, true) === 'yes';
    }

    public function priceFor(\WC_Product $product): float
    {
        $source   = $this->sourceOf($product) ?? $product;
        $override = (string) $source->get_meta(Area::META_SAMPLE_PRICE, true);

        return $override !== '' ? max(0.0, (float) $override) : $this->settings->samplePrice();
    }

    /**
     * Sample price as the shop displays prices (incl. or excl. tax), taxed like
     * the internal product the cart line is charged as.
     */
    public function displayPrice(\WC_Product $product): float
    {
        // Before the first sample exists, an unsaved simple product has the same
        // default tax status and class, without creating anything on a page view.
        $sample = wc_get_product($this->sampleProduct->existingId());

        return (float) wc_get_price_to_display($sample instanceof \WC_Product ? $sample : new \WC_Product_Simple(), ['price' => $this->priceFor($product)]);
    }

    public function renderButton(): void
    {
        global $product;
        if (! $product instanceof \WC_Product || ! $this->available($product)) {
            return;
        }

        wp_enqueue_style('coverage');
        wp_enqueue_script('coverage');

        $label = sprintf(
            /* translators: %s: formatted sample price, e.g. 4,00 zl */
            __('Order a sample of this colour (%s)', 'coverage'),
            wc_price($this->displayPrice($product)),
        );
        ?>
        <form class="coverage-sample" method="post" action="<?php echo esc_url((string) get_permalink($product->get_id())); ?>">
            <input type="hidden" name="coverage_sample" value="<?php echo esc_attr((string) $product->get_id()); ?>" />
            <button type="submit" class="button wp-element-button coverage-sample__button"><?php echo wp_kses_post($label); ?></button>
        </form>
        <?php
    }

    /**
     * Classic form post, mirroring WooCommerce's own add-to-cart handler, which
     * carries no nonce either (a nonce breaks on cached product pages, and
     * adding a sample to the shopper's own cart is not a privileged action).
     */
    public function handleForm(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (! isset($_POST['coverage_sample']) || ! function_exists('WC') || WC()->cart === null) {
            return;
        }

        $sourceId    = absint(wp_unslash($_POST['coverage_sample']));
        $variationId = isset($_POST['variation_id']) ? absint(wp_unslash($_POST['variation_id'])) : 0;
        $attributes  = [];
        foreach ($_POST as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'attribute_') && is_scalar($value)) {
                $attributes[sanitize_title($key)] = wc_clean(wp_unslash((string) $value));
            }
        }
        // phpcs:enable

        if (! $this->add($sourceId, $variationId, $attributes)) {
            return;
        }

        $target = get_option('woocommerce_cart_redirect_after_add') === 'yes'
            ? wc_get_cart_url()
            : (string) get_permalink($sourceId);
        wp_safe_redirect($target);
        exit;
    }

    /**
     * Add one sample line for a source product (and variation).
     *
     * @param array<string, mixed> $postedAttributes attribute_* values from the product form.
     */
    public function add(int $sourceId, int $variationId = 0, array $postedAttributes = []): bool
    {
        $source = wc_get_product($sourceId);
        if (! $source instanceof \WC_Product || $source->get_parent_id() > 0 || ! $this->available($source)) {
            wc_add_notice(__('Samples are not available for this product.', 'coverage'), 'error');
            return false;
        }

        $attributes = [];
        if ($source->is_type('variable')) {
            $variation  = wc_get_product($variationId);
            $attributes = $variation instanceof \WC_Product_Variation && $variation->get_parent_id() === $sourceId && $variation->get_status() === 'publish'
                ? Area::variationAttributes($variation, $postedAttributes)
                : null;
            if ($attributes === null) {
                wc_add_notice(__('Choose the product options first, then order a sample of that variant.', 'coverage'), 'error');
                return false;
            }
        } else {
            $variationId = 0;
        }

        $lines = $this->lines();
        foreach ($lines as $line) {
            if ((int) $line['source'] === $sourceId && (int) $line['variation'] === $variationId && ($line['attributes'] ?? []) === $attributes) {
                wc_add_notice(__('A sample of this product is already in your cart.', 'coverage'), 'notice');
                return false;
            }
        }
        if (count($lines) >= $this->settings->sampleMax()) {
            wc_add_notice($this->limitMessage(), 'error');
            return false;
        }

        $this->adding = true;
        try {
            $key = WC()->cart->add_to_cart($this->sampleProduct->id(), 1, 0, [], [
                self::CART_KEY => [
                    'source'     => $sourceId,
                    'variation'  => $variationId,
                    'attributes' => $attributes,
                ],
            ]);
        } catch (\Exception $e) {
            wc_add_notice(esc_html($e->getMessage()), 'error');
            $key = false;
        } finally {
            $this->adding = false;
        }

        if ($key === false) {
            return false;
        }

        wc_add_notice(sprintf(
            '<a href="%1$s" tabindex="1" class="button wc-forward">%2$s</a> %3$s',
            esc_url(wc_get_cart_url()),
            esc_html__('View cart', 'coverage'),
            /* translators: %s: product name */
            esc_html(sprintf(__('A sample of "%s" has been added to your cart.', 'coverage'), $source->get_name())),
        ), 'success');

        return true;
    }

    /**
     * The internal product can only be added through add(); this rejects direct
     * add-to-cart links and Store API requests for it.
     */
    public function blockDirectAdd(bool $passed, int $productId): bool
    {
        if ($passed && ! $this->adding && $this->sampleProduct->isSample($productId)) {
            wc_add_notice(__('Samples can only be ordered from a product page.', 'coverage'), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function applyToLine(array $item): array
    {
        $source = $this->lineSource($item);
        if ($source === null || ! ($item['data'] ?? null) instanceof \WC_Product) {
            return $item;
        }

        /** @var \WC_Product $data */
        $data      = $item['data'];
        $sample    = (array) $item[self::CART_KEY];
        $variation = (int) ($sample['variation'] ?? 0) > 0 ? wc_get_product((int) $sample['variation']) : null;
        $image     = $variation instanceof \WC_Product && $variation->get_image_id() ? $variation->get_image_id() : $source->get_image_id();
        $price     = (string) $this->priceFor($source);

        /* translators: %s: product name */
        $data->set_name(sprintf(__('Sample: %s', 'coverage'), $source->get_name()));
        $data->set_regular_price($price);
        $data->set_price($price);
        $data->set_sold_individually(true);
        $data->set_manage_stock(false);
        $data->set_stock_status('instock');
        if ($image) {
            $data->set_image_id($image);
        }
        if ($this->settings->sampleShippingClass() > 0) {
            $data->set_shipping_class_id($this->settings->sampleShippingClass());
        }

        return $item;
    }

    /**
     * Remove sample lines added without a source, or whose source vanished or
     * no longer offers samples.
     */
    public function dropInvalidLines(\WC_Cart $cart): void
    {
        foreach ($cart->get_cart() as $key => $item) {
            $isSampleProduct = $this->sampleProduct->isSample((int) $item['product_id']);
            $hasSource       = isset($item[self::CART_KEY]);
            $source          = $this->lineSource($item);
            if (($isSampleProduct || $hasSource) && ($source === null || ! $this->available($source))) {
                $cart->remove_cart_item($key);
            }
        }
    }

    public function reapplyPrices(\WC_Cart $cart): void
    {
        foreach ($cart->get_cart() as $item) {
            $source = $this->lineSource($item);
            if ($source !== null && $item['data'] instanceof \WC_Product) {
                $item['data']->set_price((string) $this->priceFor($source));
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $itemData
     * @param array<string, mixed>             $item
     * @return array<int, array<string, mixed>>
     */
    public function itemData(array $itemData, array $item): array
    {
        $source = $this->lineSource($item);
        if ($source === null) {
            return $itemData;
        }
        foreach ((array) ($item[self::CART_KEY]['attributes'] ?? []) as $key => $value) {
            $taxonomy   = substr((string) $key, strlen('attribute_'));
            $term       = taxonomy_exists($taxonomy) ? get_term_by('slug', (string) $value, $taxonomy) : false;
            $itemData[] = [
                'key'   => wc_attribute_label($taxonomy, $source),
                'value' => $term instanceof \WP_Term ? $term->name : (string) $value,
            ];
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function permalink(string $url, array $item): string
    {
        $source = $this->lineSource($item);
        if ($source === null) {
            return $url;
        }
        $variation = wc_get_product((int) ($item[self::CART_KEY]['variation'] ?? 0));

        return $variation instanceof \WC_Product_Variation ? $variation->get_permalink() : $source->get_permalink();
    }

    /**
     * @param array<string, mixed>|null $item
     */
    public function quantityEditable(bool $editable, \WC_Product $product, ?array $item = null): bool
    {
        return is_array($item) && isset($item[self::CART_KEY]) ? false : $editable;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveOrderItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values): void
    {
        if ($this->lineSource($values) === null) {
            return;
        }
        $sample = (array) $values[self::CART_KEY];
        $item->add_meta_data('_coverage_sample_source', (string) (int) $sample['source'], true);
        $item->add_meta_data('_coverage_sample_variation', (string) (int) ($sample['variation'] ?? 0), true);
        // Same storage as a variation line: WooCommerce shows the attribute
        // label and term name in emails, My Account and the order screen.
        foreach ((array) ($sample['attributes'] ?? []) as $key => $value) {
            $item->add_meta_data(substr((string) $key, strlen('attribute_')), (string) $value, true);
        }
    }

    public function checkLimit(): void
    {
        if (count($this->lines()) > $this->settings->sampleMax()) {
            wc_add_notice($this->limitMessage(), 'error');
        }
    }

    /**
     * @return list<array<string, mixed>> Sample data of every sample line in the cart.
     */
    private function lines(): array
    {
        if (! function_exists('WC') || WC()->cart === null) {
            return [];
        }
        $out = [];
        foreach (WC()->cart->get_cart() as $item) {
            if (isset($item[self::CART_KEY]) && is_array($item[self::CART_KEY])) {
                $out[] = $item[self::CART_KEY];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function lineSource(array $item): ?\WC_Product
    {
        if (! isset($item[self::CART_KEY]['source']) || ! $this->sampleProduct->isSample((int) ($item['product_id'] ?? 0))) {
            return null;
        }
        $source = wc_get_product((int) $item[self::CART_KEY]['source']);

        return $source instanceof \WC_Product ? $source : null;
    }

    private function sourceOf(\WC_Product $product): ?\WC_Product
    {
        if ($product->get_parent_id() <= 0) {
            return $product;
        }
        $parent = wc_get_product($product->get_parent_id());

        return $parent instanceof \WC_Product ? $parent : null;
    }

    private function limitMessage(): string
    {
        return sprintf(
            /* translators: %d: maximum number of samples per order */
            _n('You can order up to %d sample per order.', 'You can order up to %d samples per order.', $this->settings->sampleMax(), 'coverage'),
            $this->settings->sampleMax(),
        );
    }
}

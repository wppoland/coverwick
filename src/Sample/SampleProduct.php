<?php

declare(strict_types=1);

namespace Coverage\Sample;

use Coverage\Contract\HasHooks;
use Coverage\Settings;

defined('ABSPATH') || exit;

/**
 * The hidden internal product every sample cart line points at.
 *
 * Created on first use and recreated if it was trashed or deleted. It is
 * published (WooCommerce only sells published products) but hidden from the
 * catalogue, search and sitemaps, and its own page redirects to the shop.
 */
final class SampleProduct implements HasHooks
{
    public const OPTION = 'coverage_sample_product_id';
    public const FLAG   = '_coverage_internal_sample';

    private ?int $cached = null;

    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        add_action('template_redirect', [$this, 'redirectSinglePage']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'excludeFromSitemap'], 10, 2);
    }

    /**
     * Existing sample product id, or 0 when none is valid. Never creates.
     */
    public function existingId(): int
    {
        if ($this->cached !== null) {
            return $this->cached;
        }
        $id = (int) get_option(self::OPTION, 0);
        if ($id <= 0 || get_post_status($id) !== 'publish' || get_post_meta($id, self::FLAG, true) !== 'yes') {
            $id = 0;
        }

        return $this->cached = $id;
    }

    public function isSample(int $productId): bool
    {
        return $productId > 0 && $productId === $this->existingId();
    }

    /**
     * Sample product id, creating the product when needed.
     */
    public function id(): int
    {
        $id = $this->existingId();
        if ($id > 0) {
            return $id;
        }

        $product = new \WC_Product_Simple();
        $product->set_name(__('Sample (internal product used by Coverage Calculator, do not delete)', 'coverage'));
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_regular_price(wc_format_decimal($this->settings->samplePrice()));
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        $product->set_sold_individually(true);
        $product->set_virtual(false);
        $product->set_reviews_allowed(false);
        $product->set_shipping_class_id($this->settings->sampleShippingClass());
        $product->update_meta_data(self::FLAG, 'yes');
        $id = $product->save();

        update_option(self::OPTION, $id, false);

        return $this->cached = $id;
    }

    public function redirectSinglePage(): void
    {
        if (is_singular('product') && $this->isSample((int) get_queried_object_id())) {
            wp_safe_redirect(wc_get_page_permalink('shop'));
            exit;
        }
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function excludeFromSitemap(array $args, string $postType): array
    {
        $id = $this->existingId();
        if ($postType === 'product' && $id > 0) {
            $exclude              = isset($args['post__not_in']) && is_array($args['post__not_in']) ? $args['post__not_in'] : [];
            $args['post__not_in'] = array_merge($exclude, [$id]);
        }

        return $args;
    }
}

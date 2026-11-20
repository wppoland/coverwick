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
 * published (WooCommerce only sells published products) but kept out of every
 * front-end and REST product query: catalogue, search, feeds, the core sitemap,
 * /wp/v2/product and the Store API listing. Its own URL therefore 404s.
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
        add_action('pre_get_posts', [$this, 'excludeFromQueries']);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'excludeFromYoastSitemap']);
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
        $product->set_name(__('Sample (internal product used by Coverwick, do not delete)', 'coverwick'));
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

        update_option(self::OPTION, $id, true);

        return $this->cached = $id;
    }

    /**
     * Front-end, REST and cron product queries never return the internal
     * product. wp-admin keeps it visible so the merchant can still find it.
     */
    public function excludeFromQueries(\WP_Query $query): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }
        $types = (array) $query->get('post_type');
        $hit   = array_intersect($types, ['product', 'any']) !== []
            || ($query->is_search() && array_filter($types) === []);
        if (! $hit) {
            return;
        }
        $id = $this->existingId();
        if ($id > 0) {
            $exclude = (array) $query->get('post__not_in');
            $query->set('post__not_in', array_merge($exclude, [$id]));
        }
    }

    /**
     * @param array<int, int>|mixed $ids
     * @return array<int, int>|mixed
     */
    public function excludeFromYoastSitemap($ids)
    {
        $id = $this->existingId();

        return $id > 0 && is_array($ids) ? array_merge($ids, [$id]) : $ids;
    }
}

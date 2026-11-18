<?php

declare(strict_types=1);

namespace Coverage\Product;

use Coverage\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Keeps `_coverage_price_per_m2` on the parent product current. The key is a
 * contract read by external filters (sieve facets): keep it, keep it numeric
 * with a dot decimal, and delete it when the product has no coverage.
 *
 * Writes go through update_post_meta() rather than $product->save() so the
 * product update hooks this listens to never recurse.
 */
final class PricePerAreaSync implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('woocommerce_new_product', [$this, 'sync'], 20);
        add_action('woocommerce_update_product', [$this, 'sync'], 20);
        add_action('woocommerce_new_product_variation', [$this, 'syncParentOf'], 20);
        add_action('woocommerce_update_product_variation', [$this, 'syncParentOf'], 20);
        // Admin variation save writes our meta during processing; the variable
        // product's children cache is only reset after all variations are saved.
        add_action('woocommerce_ajax_save_product_variations', [$this, 'sync'], 20);
        // A deleted, trashed or restored variation changes the parent's lowest value.
        add_action('deleted_post', [$this, 'syncAfterDelete'], 20, 2);
        add_action('trashed_post', [$this, 'syncAfterStatusChange'], 20);
        add_action('untrashed_post', [$this, 'syncAfterStatusChange'], 20);
        // Scheduled sales: WC saves each product, these catch the batch afterwards.
        add_action('wc_after_products_starting_sales', [$this, 'syncMany'], 20);
        add_action('wc_after_products_ending_sales', [$this, 'syncMany'], 20);
    }

    public function syncParentOf(int $variationId): void
    {
        $parentId = (int) wp_get_post_parent_id($variationId);
        if ($parentId > 0) {
            $this->sync($parentId);
        }
    }

    public function syncAfterDelete(int $postId, ?\WP_Post $post = null): void
    {
        if ($post instanceof \WP_Post && $post->post_type === 'product_variation' && $post->post_parent > 0) {
            $this->sync((int) $post->post_parent);
        }
    }

    public function syncAfterStatusChange(int $postId): void
    {
        if (get_post_type($postId) === 'product_variation') {
            $this->syncParentOf($postId);
        }
    }

    /**
     * @param array<int|string, int|string> $productIds
     */
    public function syncMany(array $productIds): void
    {
        $done = [];
        foreach ($productIds as $id) {
            $parentId = (int) wp_get_post_parent_id((int) $id);
            $target   = $parentId > 0 ? $parentId : (int) $id;
            if ($target > 0 && ! isset($done[$target])) {
                $done[$target] = true;
                $this->sync($target);
            }
        }
    }

    public function sync(int $productId): void
    {
        $product = wc_get_product($productId);
        if (! $product instanceof \WC_Product) {
            return;
        }
        if ($product->get_parent_id() > 0) {
            $this->sync($product->get_parent_id());
            return;
        }

        $value = Area::lowestPricePerArea($product);
        if ($value === null) {
            delete_post_meta($productId, Area::META_PRICE_PER_M2);
            return;
        }

        update_post_meta($productId, Area::META_PRICE_PER_M2, wc_format_decimal($value, wc_get_price_decimals()));
    }
}

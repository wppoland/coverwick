<?php
/**
 * Uninstall cleanup for Coverage Calculator.
 *
 * Removes the plugin's options and its hidden internal sample product. Product
 * coverage values, the computed price per area and order item meta are store
 * data and stay, so a reinstall picks them up again.
 *
 * @package Coverage
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

function coverage_uninstall_site(): void
{
    $sample = (int) get_option('coverage_sample_product_id', 0);
    if ($sample > 0 && get_post_meta($sample, '_coverage_internal_sample', true) === 'yes') {
        wp_delete_post($sample, true);
    }
    delete_option('coverage_sample_product_id');
    delete_option('coverage_settings');
    delete_option('coverage_db_version');
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $coverage_site_id) {
        switch_to_blog((int) $coverage_site_id);
        coverage_uninstall_site();
        restore_current_blog();
    }
    unset($coverage_site_id);
} else {
    coverage_uninstall_site();
}

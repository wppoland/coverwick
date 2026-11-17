<?php
/**
 * Default settings, merged under the option key `coverage_settings`.
 *
 * @package Coverage
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Area unit typed by the merchant; a trailing 2 renders as a superscript.
    'unit'                  => 'm2',
    'samples_enabled'       => true,
    // Default sample price (store currency, dot decimal), overridable per product.
    'sample_price'          => '4',
    'sample_max'            => 5,
    // Shipping class term id applied to sample lines, 0 keeps the store default.
    'sample_shipping_class' => 0,
];

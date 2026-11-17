<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Coverage\Contract\HasHooks.
 *
 * @package Coverage
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Coverage\Admin\ProductFields;
use Coverage\Admin\SettingsPage;
use Coverage\Cart\CartArea;
use Coverage\Frontend\ProductPage;
use Coverage\Product\PricePerAreaSync;
use Coverage\Sample\SampleProduct;
use Coverage\Sample\Samples;

defined('ABSPATH') || exit;

return [
    SettingsPage::class,
    ProductFields::class,
    PricePerAreaSync::class,
    ProductPage::class,
    CartArea::class,
    SampleProduct::class,
    Samples::class,
];

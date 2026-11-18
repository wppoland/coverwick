<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container.
 *
 * @package Coverage
 */

declare(strict_types=1);

use Coverage\Admin\ProductFields;
use Coverage\Admin\SettingsPage;
use Coverage\Cart\CartArea;
use Coverage\Container;
use Coverage\Frontend\ProductPage;
use Coverage\Migrator;
use Coverage\Product\PricePerAreaSync;
use Coverage\Sample\SampleProduct;
use Coverage\Sample\Samples;
use Coverage\Settings;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());
    $c->singleton(Settings::class, static fn (): Settings => new Settings());
    $c->singleton(SettingsPage::class, static fn (Container $c): SettingsPage => new SettingsPage($c->get(Settings::class)));
    $c->singleton(ProductFields::class, static fn (Container $c): ProductFields => new ProductFields($c->get(Settings::class)));
    $c->singleton(PricePerAreaSync::class, static fn (): PricePerAreaSync => new PricePerAreaSync());
    $c->singleton(ProductPage::class, static fn (Container $c): ProductPage => new ProductPage($c->get(Settings::class)));
    $c->singleton(CartArea::class, static fn (Container $c): CartArea => new CartArea($c->get(Settings::class)));
    $c->singleton(SampleProduct::class, static fn (Container $c): SampleProduct => new SampleProduct($c->get(Settings::class)));
    $c->singleton(Samples::class, static fn (Container $c): Samples => new Samples($c->get(Settings::class), $c->get(SampleProduct::class)));
};

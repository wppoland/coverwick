<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container.
 *
 * @package Coverage
 */

declare(strict_types=1);

use Coverage\Container;
use Coverage\Migrator;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());
};

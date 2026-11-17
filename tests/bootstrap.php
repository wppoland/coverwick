<?php
/**
 * Unit test bootstrap: pure classes only, no WordPress.
 *
 * @package Coverage
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require dirname(__DIR__) . '/vendor/autoload.php';

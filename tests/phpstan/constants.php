<?php
/**
 * Constants needed by PHPStan to analyse the plugin without bootstrapping WordPress.
 *
 * @package Coverage
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('COVERAGE_DIR')) {
        define('COVERAGE_DIR', '/tmp/coverage/');
    }
    if (! defined('COVERAGE_URL')) {
        define('COVERAGE_URL', 'https://example.test/wp-content/plugins/coverage/');
    }
}

namespace Coverage {
    if (! defined('Coverage\\VERSION')) {
        define('Coverage\\VERSION', '0.1.1');
    }
    if (! defined('Coverage\\PLUGIN_FILE')) {
        define('Coverage\\PLUGIN_FILE', '/tmp/coverage/coverage.php');
    }
}

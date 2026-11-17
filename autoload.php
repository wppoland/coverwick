<?php
/**
 * Autoloading: prefer Composer's vendor autoloader (optimized classmap) and
 * fall back to a minimal PSR-4 autoloader so the plugin still boots if vendor/
 * is absent.
 *
 * @package Coverage
 */

declare(strict_types=1);

namespace Coverage;

defined('ABSPATH') || exit;

$coverage_composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($coverage_composer)) {
    require_once $coverage_composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Coverage\\';
    $len    = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

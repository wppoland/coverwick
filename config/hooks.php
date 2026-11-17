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

defined('ABSPATH') || exit;

return [
    // WaitlistService::class,
];

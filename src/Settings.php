<?php

declare(strict_types=1);

namespace Coverage;

use Coverage\Support\Calculator;

defined('ABSPATH') || exit;

/**
 * Read access to the `coverage_settings` option merged over config/defaults.php.
 */
final class Settings
{
    public const OPTION = 'coverage_settings';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return require __DIR__ . '/../config/defaults.php';
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $stored      = get_option(self::OPTION, []);
            $this->cache = array_merge(self::defaults(), is_array($stored) ? $stored : []);
        }

        return $this->cache;
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    public function unit(): string
    {
        return Calculator::displayUnit((string) $this->all()['unit']);
    }

    public function samplesEnabled(): bool
    {
        return (bool) $this->all()['samples_enabled'];
    }

    public function samplePrice(): float
    {
        return Calculator::parseDecimal((string) $this->all()['sample_price']);
    }

    public function sampleMax(): int
    {
        return max(1, (int) $this->all()['sample_max']);
    }

    public function sampleShippingClass(): int
    {
        return max(0, (int) $this->all()['sample_shipping_class']);
    }
}

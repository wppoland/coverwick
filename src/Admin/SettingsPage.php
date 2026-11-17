<?php

declare(strict_types=1);

namespace Coverage\Admin;

use Coverage\Contract\HasHooks;
use Coverage\Settings;
use Coverage\Support\Calculator;

defined('ABSPATH') || exit;

/**
 * WooCommerce > Coverage Calculator settings page.
 */
final class SettingsPage implements HasHooks
{
    private const PAGE = 'coverage-settings';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('plugin_action_links_' . plugin_basename(\Coverage\PLUGIN_FILE), [$this, 'actionLinks']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Coverage Calculator', 'coverage'),
            __('Coverage Calculator', 'coverage'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    /**
     * @param array<int|string, string> $links
     * @return array<int|string, string>
     */
    public function actionLinks(array $links): array
    {
        $url = admin_url('admin.php?page=' . self::PAGE);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'coverage') . '</a>');

        return $links;
    }

    public function registerSettings(): void
    {
        register_setting(self::PAGE, Settings::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);

        add_filter('option_page_capability_' . self::PAGE, static fn (): string => 'manage_woocommerce');
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $unit  = sanitize_text_field((string) ($input['unit'] ?? ''));

        $this->settings->flush();

        return [
            'unit'                  => $unit === '' ? 'm2' : $unit,
            'samples_enabled'       => ! empty($input['samples_enabled']),
            'sample_price'          => wc_format_decimal(Calculator::parseDecimal((string) ($input['sample_price'] ?? '0'))),
            'sample_max'            => max(1, absint($input['sample_max'] ?? 1)),
            'sample_shipping_class' => absint($input['sample_shipping_class'] ?? 0),
        ];
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $s       = $this->settings->all();
        $name    = static fn (string $key): string => Settings::OPTION . '[' . $key . ']';
        $classes = WC()->shipping()->get_shipping_classes();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('Products with a coverage value get linked pieces and area inputs, a price per area unit and optional paid samples. Set the coverage per unit on each product, in the General tab or per variation.', 'coverage'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <h2><?php esc_html_e('Calculator', 'coverage'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="coverage_unit"><?php esc_html_e('Area unit', 'coverage'); ?></label></th>
                        <td>
                            <input type="text" class="small-text" id="coverage_unit" name="<?php echo esc_attr($name('unit')); ?>" value="<?php echo esc_attr((string) $s['unit']); ?>" />
                            <p class="description"><?php esc_html_e('Shown next to area values. A trailing 2 or 3 is displayed as a superscript.', 'coverage'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Paid samples', 'coverage'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable samples', 'coverage'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($name('samples_enabled')); ?>" value="1" <?php checked((bool) $s['samples_enabled']); ?> />
                                <?php esc_html_e('Show the sample button on products marked "Sample available".', 'coverage'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coverage_sample_price"><?php esc_html_e('Default sample price', 'coverage'); ?></label></th>
                        <td>
                            <input type="text" class="small-text" id="coverage_sample_price" name="<?php echo esc_attr($name('sample_price')); ?>" value="<?php echo esc_attr(wc_format_localized_price((string) $s['sample_price'])); ?>" />
                            <?php echo esc_html(get_woocommerce_currency_symbol()); ?>
                            <p class="description"><?php esc_html_e('Used when a product does not set its own sample price.', 'coverage'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coverage_sample_max"><?php esc_html_e('Samples per order', 'coverage'); ?></label></th>
                        <td>
                            <input type="number" min="1" step="1" class="small-text" id="coverage_sample_max" name="<?php echo esc_attr($name('sample_max')); ?>" value="<?php echo esc_attr((string) $s['sample_max']); ?>" />
                            <p class="description"><?php esc_html_e('Maximum number of sample lines in one cart. Each product (and variation) can be sampled once.', 'coverage'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="coverage_sample_shipping_class"><?php esc_html_e('Sample shipping class', 'coverage'); ?></label></th>
                        <td>
                            <select id="coverage_sample_shipping_class" name="<?php echo esc_attr($name('sample_shipping_class')); ?>">
                                <option value="0"><?php esc_html_e('No shipping class', 'coverage'); ?></option>
                                <?php foreach ($classes as $class) : ?>
                                    <option value="<?php echo esc_attr((string) $class->term_id); ?>" <?php selected((int) $s['sample_shipping_class'], (int) $class->term_id); ?>><?php echo esc_html($class->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Applied to sample lines in the cart, e.g. a cheap letter or parcel rate.', 'coverage'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

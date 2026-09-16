<?php

/**
 * Plugin Name: WP Adsterra Dashboard
 * Plugin URI: https://wordpress-plugins.luongovincenzo.it/plugin/wp-adsterra-dashboard
 * Description: Shows your Adsterra publisher earnings in the WordPress dashboard.
 * Version: 4.0.0
 * Author: Vincenzo Luongo
 * Author URI: https://www.luongovincenzo.it/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-adsterra-dashboard
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ADSTERRA_DASHBOARD_VERSION', '4.0.0');
define('ADSTERRA_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX', 'wp_adsterra_dashboard_option');
define('ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP', 'wp-adsterra-dashboard-settings-group');

class WPAdsterraDashboard {

    const PAGE_SLUG = 'wp-adsterra-dashboard';
    const CACHE_PREFIX = 'adsterra_widget_cache_';

    public function __construct() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);
        add_action('wp_ajax_adsterra_update_month_filter', [$this, 'wp_adsterra_update_month_filter_action']);
        add_action('wp_ajax_adsterra_refresh_cache', [$this, 'wp_adsterra_refresh_cache_action']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_actions']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('wp-adsterra-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function option($name) {
        return get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_' . $name);
    }

    private function domain_id() {
        $domain_id = (string) $this->option('domain_id');
        return ctype_digit($domain_id) ? $domain_id : 'all';
    }

    private function settings_url() {
        return admin_url('options-general.php?page=' . self::PAGE_SLUG);
    }

    // First day of the month picked in the widget, in the site timezone. Defaults to the current month.
    private function selected_month() {
        $current = new DateTimeImmutable('first day of this month 00:00', wp_timezone());
        $filter = (int) $this->option('widget_month_filter');

        if ($filter <= 0 || $filter > time()) {
            return $current;
        }

        return (new DateTimeImmutable('@' . $filter))->setTimezone(wp_timezone())->modify('first day of this month 00:00');
    }

    private function cache_key(DateTimeImmutable $month) {
        return self::CACHE_PREFIX . md5($this->option('token') . '|' . $this->domain_id() . '|' . $month->format('Y-m'));
    }

    public function wp_adsterra_update_month_filter_action() {
        check_ajax_referer('adsterra_month_filter_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }

        $month = isset($_POST['filter_month']) ? absint(wp_unslash($_POST['filter_month'])) : 0;

        update_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter', $month);

        wp_send_json_success();
    }

    public function wp_adsterra_refresh_cache_action() {
        check_ajax_referer('adsterra_refresh_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(null, 403);
        }

        delete_transient($this->cache_key($this->selected_month()));

        wp_send_json_success();
    }

    public function enqueue_assets($hook) {
        if (!current_user_can('manage_options') || !in_array($hook, ['index.php', 'settings_page_' . self::PAGE_SLUG], true)) {
            return;
        }

        wp_enqueue_style('wp-adsterra-dashboard', plugins_url('css/style.css', __FILE__), [], ADSTERRA_DASHBOARD_VERSION);

        if ($hook !== 'index.php' || !$this->option('enabled') || !$this->option('token')) {
            return;
        }

        wp_enqueue_script('wp-adsterra-dashboard-chartjs', plugins_url('js/chartjs.js', __FILE__), [], '4.4.7', true);
        wp_enqueue_script('wp-adsterra-dashboard', plugins_url('js/main.js', __FILE__), ['wp-adsterra-dashboard-chartjs'], ADSTERRA_DASHBOARD_VERSION, true);

        wp_localize_script('wp-adsterra-dashboard', 'adsterraDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'monthNonce' => wp_create_nonce('adsterra_month_filter_nonce'),
            'refreshNonce' => wp_create_nonce('adsterra_refresh_nonce'),
            'i18n' => [
                'refreshing' => __('Refreshing…', 'wp-adsterra-dashboard'),
                'error' => __('Something went wrong. Please try again.', 'wp-adsterra-dashboard'),
                'total' => __('Total', 'wp-adsterra-dashboard'),
            ],
        ]);
    }

    public function add_plugin_actions($links) {
        $links[] = '<a href="' . esc_url($this->settings_url()) . '">' . esc_html__('Settings', 'wp-adsterra-dashboard') . '</a>';
        return $links;
    }

    public function create_admin_menu() {
        add_options_page(
            __('Adsterra Settings', 'wp-adsterra-dashboard'),
            __('Adsterra Settings', 'wp-adsterra-dashboard'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'viewAdminSettingsPage']
        );
    }

    // On invalid input the saved value is kept: returning false would overwrite it with an empty one
    public function optionTokenValidate($value) {
        $token = sanitize_text_field(trim((string) $value));

        if (!preg_match('/^[a-z0-9]{32}$/', $token)) {
            add_settings_error(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token', 'invalid_token', __('Invalid API token: it must be exactly 32 lowercase letters and digits. The previous token has been kept.', 'wp-adsterra-dashboard'));
            return $this->option('token');
        }

        return $token;
    }

    public function optionDomainIdValidate($value) {
        $domain_id = sanitize_text_field((string) $value);

        if ($domain_id === '' || $domain_id === 'all') {
            return 'all';
        }

        if (!ctype_digit($domain_id)) {
            add_settings_error(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id', 'invalid_domain', __('Invalid domain. The previous one has been kept.', 'wp-adsterra-dashboard'));
            return $this->option('domain_id');
        }

        return $domain_id;
    }

    public function register_settings() {
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'optionTokenValidate'],
        ]);
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'optionDomainIdValidate'],
        ]);
    }

    public function dashboard_widget() {
        // Earnings are for administrators only
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget('adsterra_dashboard_widget', __('Adsterra Earnings Dashboard', 'wp-adsterra-dashboard'), [$this, 'adsterra_dashboard_widget']);
        }
    }

    public function viewAdminSettingsPage() {
        $token = $this->option('token');
        $domain_id = $this->domain_id();
        $domains = [];
        $errorMessage = null;

        if ($token) {
            $domains = (new WP_Adsterra_Dashboard_API_Client($token))->getDomains();

            if (is_wp_error($domains)) {
                $errorMessage = $domains->get_error_message();
                $domains = [];
            }
        }

        $name = ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX;
?>
        <div class="adsterra-settings-wrap">
            <div class="adsterra-settings-header">
                <h1>⚡ <?php esc_html_e('Adsterra Dashboard Settings', 'wp-adsterra-dashboard'); ?></h1>
                <p><?php esc_html_e('Connect your Adsterra account to see your earnings in the WordPress dashboard.', 'wp-adsterra-dashboard'); ?></p>
            </div>
            <hr class="wp-header-end">

            <?php if ($errorMessage) { ?>
                <div class="adsterra-alert adsterra-alert-error">
                    <span class="adsterra-alert-icon">⚠️</span>
                    <span><?php echo esc_html($errorMessage); ?></span>
                </div>
            <?php } ?>

            <div class="adsterra-settings-card">
                <form method="post" action="options.php">
                    <?php settings_fields(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP); ?>

                    <div class="adsterra-form-group">
                        <span class="adsterra-form-label"><?php esc_html_e('Enable Dashboard Widget', 'wp-adsterra-dashboard'); ?></span>
                        <div class="adsterra-toggle-wrapper">
                            <label class="adsterra-toggle">
                                <input type="checkbox" value="1" name="<?php echo esc_attr($name . '_enabled'); ?>" <?php checked((bool) $this->option('enabled')); ?> aria-label="<?php esc_attr_e('Enable Dashboard Widget', 'wp-adsterra-dashboard'); ?>" />
                                <span class="adsterra-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="adsterra-form-group">
                        <label class="adsterra-form-label" for="adsterra_token"><?php esc_html_e('API Token', 'wp-adsterra-dashboard'); ?></label>
                        <input type="text"
                               id="adsterra_token"
                               class="adsterra-form-input"
                               name="<?php echo esc_attr($name . '_token'); ?>"
                               value="<?php echo esc_attr($token); ?>"
                               placeholder="<?php esc_attr_e('32-character API token', 'wp-adsterra-dashboard'); ?>"
                               autocomplete="off"
                               required />
                        <p class="adsterra-description">
                            🔑 <?php
                            printf(
                                /* translators: %s: URL of the Adsterra API token page */
                                wp_kses(__('Generate a token on the <a href="%s" target="_blank" rel="noopener noreferrer">Adsterra API page</a> and paste it here.', 'wp-adsterra-dashboard'), ['a' => ['href' => [], 'target' => [], 'rel' => []]]),
                                'https://beta.publishers.adsterra.com/api-token'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="adsterra-form-group">
                        <label class="adsterra-form-label" for="adsterra_domain"><?php esc_html_e('Domain', 'wp-adsterra-dashboard'); ?></label>
                        <?php if ($domains) { ?>
                            <select id="adsterra_domain" class="adsterra-form-select" name="<?php echo esc_attr($name . '_domain_id'); ?>">
                                <option value="all" <?php selected($domain_id, 'all'); ?>>🌐 <?php esc_html_e('All Domains', 'wp-adsterra-dashboard'); ?></option>
                                <?php foreach ($domains as $domain) { ?>
                                    <option value="<?php echo esc_attr($domain['id']); ?>" <?php selected($domain_id, (string) $domain['id']); ?>><?php echo esc_html($domain['title']); ?></option>
                                <?php } ?>
                            </select>
                            <p class="adsterra-description">
                                📊 <?php esc_html_e('Choose "All Domains" for combined statistics, or a single domain.', 'wp-adsterra-dashboard'); ?>
                            </p>
                        <?php } else { ?>
                            <?php // Keep the saved domain when the list cannot be loaded ?>
                            <input type="hidden" name="<?php echo esc_attr($name . '_domain_id'); ?>" value="<?php echo esc_attr($domain_id); ?>" />
                            <p class="adsterra-description">
                                ⚠️ <?php esc_html_e('Save a valid API token to choose a domain.', 'wp-adsterra-dashboard'); ?>
                            </p>
                        <?php } ?>
                    </div>

                    <div class="adsterra-submit-wrapper">
                        <button type="submit" class="adsterra-btn-primary">
                            💾 <?php esc_html_e('Save Settings', 'wp-adsterra-dashboard'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
<?php
    }

    /**
     * Daily earnings per placement for the month, cached for an hour. Errors are never cached.
     */
    private function get_stats($token, DateTimeImmutable $month) {
        $cache_key = $this->cache_key($month);
        $stats = get_transient($cache_key);

        if ($stats !== false) {
            return $stats;
        }

        // Two API calls whatever the number of domains and placements, three when placement names repeat
        $client = new WP_Adsterra_Dashboard_API_Client($token);
        $placements = $client->getPlacements();

        if (is_wp_error($placements)) {
            return $placements;
        }

        $end = $month->modify('last day of this month');
        $rows = $client->getStats($this->domain_id(), $month->format('Y-m-d'), $end->format('Y-m-d'));

        if (is_wp_error($rows)) {
            return $rows;
        }

        $titles = [];
        $placement_domains = [];
        foreach ($placements as $placement) {
            $titles[$placement['id'] ?? ''] = ($placement['alias'] ?? '') ?: ($placement['title'] ?? '');
            $placement_domains[$placement['id'] ?? ''] = $placement['domain_id'] ?? '';
        }

        $days = [];
        for ($day = $month; $day <= $end; $day = $day->modify('+1 day')) {
            $days[$day->format('Y-m-d')] = 0;
        }

        $series = [];
        $impressions = 0;
        $clicks = 0;
        $revenue = 0;

        foreach ($rows as $row) {
            $date = substr((string) ($row['date'] ?? ''), 0, 10);

            if (!isset($days[$date])) {
                continue;
            }

            $placement = (string) ($row['placement'] ?? '');
            $series[$placement] = $series[$placement] ?? $days;
            $series[$placement][$date] += (float) ($row['revenue'] ?? 0);

            $impressions += (int) ($row['impression'] ?? 0);
            $clicks += (int) ($row['clicks'] ?? 0);
            $revenue += (float) ($row['revenue'] ?? 0);
        }

        $labels = [];
        foreach (array_keys($series) as $placement) {
            $labels[$placement] = strtoupper($titles[$placement] ?? '#' . $placement);
        }

        // Placements often share a name across sites (e.g. SocialBar_1): add the domain to tell them apart
        $duplicates = array_diff_assoc($labels, array_unique($labels));
        if ($duplicates) {
            $domains = $client->getDomains();
            $domain_titles = is_wp_error($domains) ? [] : array_column($domains, 'title', 'id');

            foreach ($labels as $placement => $label) {
                if (in_array($label, $duplicates, true)) {
                    $domain = $placement_domains[$placement] ?? '';
                    $labels[$placement] .= ' · ' . ($domain_titles[$domain] ?? '#' . $domain);
                }
            }
        }

        $datasets = [];
        foreach ($series as $placement => $values) {
            $datasets[] = [
                'label' => $labels[$placement],
                'data' => array_map(function ($value) {
                    return round($value, 3);
                }, array_values($values)),
            ];
        }

        $stats = [
            'labels' => array_map(function ($date) {
                return substr($date, 8, 2);
            }, array_keys($days)),
            'datasets' => $datasets,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'revenue' => $revenue,
            'cpm' => $impressions ? $revenue / $impressions * 1000 : 0,
            'ctr' => $impressions ? $clicks / $impressions * 100 : 0,
        ];

        set_transient($cache_key, $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    public function adsterra_dashboard_widget() {
        $token = $this->option('token');
        $settings_link = ['a' => ['href' => []]];

        if (!$this->option('enabled') || !$token) {
            echo '<p>' . wp_kses(sprintf(
                /* translators: %s: settings page URL */
                __('The widget is disabled or the API token is missing. <a href="%s">Open the settings</a> to set it up.', 'wp-adsterra-dashboard'),
                esc_url($this->settings_url())
            ), $settings_link) . '</p>';
            return;
        }

        $month = $this->selected_month();
        $stats = $this->get_stats($token, $month);

        if (is_wp_error($stats)) {
            // "inline" stops WordPress from moving the notice out of the widget, to the top of the page
            echo '<div class="error notice inline"><p>' . esc_html($stats->get_error_message()) . '</p></div>';
            echo '<p>' . wp_kses(sprintf(
                /* translators: %s: settings page URL */
                __('Check your API token in the <a href="%s">settings</a>.', 'wp-adsterra-dashboard'),
                esc_url($this->settings_url())
            ), $settings_link) . '</p>';
            return;
        }

        wp_add_inline_script('wp-adsterra-dashboard', 'var adsterraDashboardChart = ' . wp_json_encode([
            'labels' => $stats['labels'],
            'datasets' => $stats['datasets'],
            /* translators: %s: month name */
            'axisTitle' => sprintf(__('Days of %s', 'wp-adsterra-dashboard'), wp_date('F', $month->getTimestamp())),
        ], JSON_HEX_TAG) . ';', 'before');

        $current = new DateTimeImmutable('first day of this month 00:00', wp_timezone());
?>
        <div id="container-box">
            <div id="containerChartjs">
                <canvas id="adsterraStatsCanvas"></canvas>
            </div>

            <div class="adsterra-controls">
                <table>
                    <tr>
                        <td style="width:30%; color: #666; font-weight: 600;">
                            <label for="adsterra_dashboard_widget_filter_month">📅 <?php esc_html_e('Filter Month:', 'wp-adsterra-dashboard'); ?></label>
                        </td>
                        <td style="width:50%;">
                            <select id="adsterra_dashboard_widget_filter_month">
                                <?php
                                for ($i = 0; $i <= 12; $i++) {
                                    $option = $current->modify("-$i month");
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($option->getTimestamp()),
                                        selected($option->format('Y-m'), $month->format('Y-m'), false),
                                        esc_html(wp_date('F Y', $option->getTimestamp()))
                                    );
                                }
                                ?>
                            </select>
                        </td>
                        <td style="width:20%; text-align: right;">
                            <button type="button" id="adsterra_refresh_cache" class="button button-secondary">
                                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> <?php esc_html_e('Refresh', 'wp-adsterra-dashboard'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="stats-grid">
                <div class="small-box">
                    <h3>👁️ <?php esc_html_e('Impressions', 'wp-adsterra-dashboard'); ?></h3>
                    <p><?php echo esc_html(number_format_i18n($stats['impressions'])); ?></p>
                </div>
                <div class="small-box">
                    <h3>🖱️ <?php esc_html_e('Clicks', 'wp-adsterra-dashboard'); ?></h3>
                    <p><?php echo esc_html(number_format_i18n($stats['clicks'])); ?></p>
                </div>
                <div class="small-box">
                    <h3>💰 <?php esc_html_e('CPM', 'wp-adsterra-dashboard'); ?></h3>
                    <p>$<?php echo esc_html(number_format_i18n($stats['cpm'], 2)); ?></p>
                </div>
                <div class="small-box">
                    <h3>📊 <?php esc_html_e('CTR', 'wp-adsterra-dashboard'); ?></h3>
                    <p><?php echo esc_html(number_format_i18n($stats['ctr'], 2)); ?>%</p>
                </div>

                <div class="small-box small-md-6">
                    <h3>💵 <?php esc_html_e('Grand Earnings', 'wp-adsterra-dashboard'); ?></h3>
                    <p>$<?php echo esc_html(number_format_i18n($stats['revenue'], 2)); ?></p>
                </div>
            </div>
        </div>
<?php
    }
}

// Everything the plugin does happens in the admin area
if (is_admin()) {
    require_once ADSTERRA_DASHBOARD_PLUGIN_DIR . 'libs/class-api-client.php';
    new WPAdsterraDashboard();
}

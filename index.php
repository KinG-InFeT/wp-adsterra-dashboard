<?php

/**
 * Plugin Name: WP Adsterra Dashboard
 * Plugin URI: https://wordpress-plugins.luongovincenzo.it/#wp-adsterra-dashboard
 * Description: WP AdsTerra Dashboard for view statistics via API
 * Version: 2.0.0
 * Author: Vincenzo Luongo
 * Author URI: https://www.luongovincenzo.it/
 * License: GPLv2 or later
 * Text Domain: wp-adsterra-dashboard
 */
if (!defined('ABSPATH')) {
    exit;
}

define("ADSTERRA_DASHBOARD_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX", 'wp_adsterra_dashboard_option');
define("ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP", 'wp-adsterra-dashboard-settings-group');

class WPAdsterraDashboard {

    protected $pluginDetails;
    protected $pluginOptions = [];

    function __construct() {

        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $this->pluginDetails = get_plugin_data(__FILE__);


        $this->pluginOptions = [
            'enabled' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'token' => trim(get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token')),
            'domain_id' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id'),
            'placements' => trim(get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_placements')) ?: 'all',
            'widget_filter_month' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);

        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_actions']);

        add_action('admin_enqueue_scripts', [$this, 'widget_dashboard_ajax_script']);
        add_action('wp_ajax_adsterra_update_month_filter', [$this, 'wp_adsterra_update_month_filter_action']);
        add_action('wp_ajax_adsterra_refresh_cache', [$this, 'wp_adsterra_refresh_cache_action']);
    }

    public function wp_adsterra_update_month_filter_action() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'adsterra_month_filter_nonce')) {
            wp_die('Security check failed');
        }

        $filter = $this->validFilter(sanitize_text_field($_POST['filter_month']));

        update_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter', $filter);

        wp_send_json_success(['message' => 'Filter updated successfully']);
    }

    public function wp_adsterra_refresh_cache_action() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'adsterra_refresh_nonce')) {
            wp_die('Security check failed');
        }

        // Clear all Adsterra cache
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adsterra_widget_cache_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_adsterra_widget_cache_%'");

        wp_send_json_success(['message' => 'Cache refreshed successfully']);
    }

    private function validFilter($timestamp) {
        if (!empty($timestamp) && ((string) (int) $timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX) && is_numeric($timestamp)) {
            return $timestamp;
        } else {
            return null;
        }
    }

    public function widget_dashboard_ajax_script($hook) {

        if ('index.php' != $hook) {
            // Only applies to dashboard panel
            return;
        }

        wp_enqueue_style('adsterra-dashboard-widget-admin-theme', plugins_url('/css/style.css', __FILE__), $this->pluginDetails['Version']);

        wp_enqueue_script('chartjs', plugins_url('/js/chartjs.js', __FILE__), ['jquery'], $this->pluginDetails['Version']);

        wp_enqueue_script('adsterra-dashboard-widget-admin-ajax-script', plugins_url('/js/main.js', __FILE__), ['jquery'], $this->pluginDetails['Version']);

        wp_localize_script('adsterra-dashboard-widget-admin-ajax-script', 'adsterra_ajax_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adsterra_month_filter_nonce'),
            'refresh_nonce' => wp_create_nonce('adsterra_refresh_nonce')
        ]);
    }

    public function add_plugin_actions($links) {
        $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wp-adsterra-dashboard%2Findex.php')) . '">Settings</a>';
        return $links;
    }

    public function create_admin_menu() {
        add_options_page('Adsterra Settings', 'Adsterra Settings', 'administrator', __FILE__, [$this, 'viewAdminSettingsPage']);
        add_action('admin_init', [$this, '_registerOptions']);
    }

    function optionTokenValidate($value) {
        $sanitized_value = sanitize_text_field(trim($value));

        if (empty($sanitized_value)) {
            add_settings_error('adsterra_plugins_option_token', 'adsterra_plugins_option_token', 'Token cannot be empty.', 'error');
            return false;
        }

        if (!preg_match("/^[a-z0-9]{32}$/", $sanitized_value)) {
            add_settings_error('adsterra_plugins_option_token', 'adsterra_plugins_option_token', 'Token invalid. Must be exactly 32 alphanumeric characters.', 'error');
            return false;
        }

        return $sanitized_value;
    }

    function optionDomainIdValidate($value) {
        $sanitized_value = sanitize_text_field($value);

        if (!empty($sanitized_value) && $sanitized_value !== 'all' && !is_numeric($sanitized_value)) {
            add_settings_error('adsterra_plugins_option_domain_id', 'adsterra_plugins_option_domain_id', 'Domain ID must be numeric or "all".', 'error');
            return false;
        }

        return $sanitized_value;
    }

    public function _registerOptions() {
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled');
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token', [$this, 'optionTokenValidate']);
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id', [$this, 'optionDomainIdValidate']);
    }

    public function dashboard_widget() {
        wp_add_dashboard_widget('adsterra_dashboard_widget', 'Adsterra Earnings Dashboard', [$this, 'adsterra_dashboard_widget']);
    }

    public function viewAdminSettingsPage() {

        $domains = [];
        $errorMessage = null;

        $pluginSettings = [
            'enabled' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'token' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token'),
            'domain_id' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id'),
            'widget_filter_month' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        if (!empty($pluginSettings['token'])) {
            require_once ADSTERRA_DASHBOARD_PLUGIN_DIR . 'libs/class-api-client.php';
            $adsterraAPIClient = new WPAdsterraDashboardAPIClient($pluginSettings['token'], $pluginSettings['domain_id']);

            $domains = $adsterraAPIClient->getDomains();

            if (!empty($domains['code']) && in_array($domains['code'], [401, 403])) {
                $errorMessage = 'Adsterra Dashboard. API Token error Code: ' . $domains['code'] . ' Message: ' . $domains['message'];

                $domains = [];
            }
        }
?>

        <style>
            .adsterra-settings-wrap {
                max-width: 1000px;
                margin: 20px 0;
            }

            .adsterra-settings-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 8px;
                margin-bottom: 30px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .adsterra-settings-header h1 {
                margin: 0 0 10px 0;
                color: white;
                font-size: 28px;
                font-weight: 600;
            }

            .adsterra-settings-header p {
                margin: 0;
                opacity: 0.9;
                font-size: 14px;
            }

            .adsterra-settings-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                padding: 30px;
            }

            .adsterra-form-group {
                margin-bottom: 25px;
                padding-bottom: 25px;
                border-bottom: 1px solid #f0f0f0;
            }

            .adsterra-form-group:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }

            .adsterra-form-label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #23282d;
                font-size: 14px;
            }

            .adsterra-form-input {
                width: 100%;
                max-width: 500px;
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                transition: border-color 0.3s;
            }

            .adsterra-form-input:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .adsterra-form-select {
                width: 100%;
                max-width: 500px;
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                background-color: white;
                transition: border-color 0.3s;
            }

            .adsterra-form-select:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .adsterra-toggle-wrapper {
                display: flex;
                align-items: center;
            }

            .adsterra-toggle {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 26px;
            }

            .adsterra-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .adsterra-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 34px;
            }

            .adsterra-toggle-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            .adsterra-toggle input:checked + .adsterra-toggle-slider {
                background-color: #667eea;
            }

            .adsterra-toggle input:checked + .adsterra-toggle-slider:before {
                transform: translateX(24px);
            }

            .adsterra-toggle-label {
                margin-left: 12px;
                color: #666;
                font-size: 14px;
            }

            .adsterra-description {
                margin-top: 8px;
                color: #666;
                font-size: 13px;
                line-height: 1.5;
            }

            .adsterra-description a {
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
            }

            .adsterra-description a:hover {
                text-decoration: underline;
            }

            .adsterra-submit-wrapper {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #f0f0f0;
            }

            .adsterra-btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 4px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .adsterra-btn-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }

            .adsterra-alert {
                padding: 15px 20px;
                border-radius: 4px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
            }

            .adsterra-alert-error {
                background-color: #fee;
                border-left: 4px solid #dc3545;
                color: #721c24;
            }

            .adsterra-alert-icon {
                margin-right: 10px;
                font-size: 18px;
            }
        </style>

        <div class="adsterra-settings-wrap">
            <div class="adsterra-settings-header">
                <h1>⚡ Adsterra Dashboard Settings</h1>
                <p>Configure your Adsterra API integration to display earnings statistics on your dashboard</p>
            </div>

            <?php if ($errorMessage) { ?>
                <div class="adsterra-alert adsterra-alert-error">
                    <span class="adsterra-alert-icon">⚠️</span>
                    <span><?php echo esc_html($errorMessage); ?></span>
                </div>
            <?php } ?>

            <div class="adsterra-settings-card">
                <form method="post" action="options.php">
                    <?php settings_fields(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP); ?>
                    <?php do_settings_sections(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP); ?>

                    <div class="adsterra-form-group">
                        <label class="adsterra-form-label">Enable Dashboard Widget</label>
                        <div class="adsterra-toggle-wrapper">
                            <label class="adsterra-toggle">
                                <input type="checkbox" <?php echo get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX; ?>_enabled" />
                                <span class="adsterra-toggle-slider"></span>
                            </label>
                            <span class="adsterra-toggle-label">
                                <?php echo get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled') ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="adsterra-form-group">
                        <label class="adsterra-form-label" for="adsterra_token">API Token</label>
                        <input type="text"
                               id="adsterra_token"
                               class="adsterra-form-input"
                               name="<?php print ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX; ?>_token"
                               value="<?php echo htmlspecialchars(get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token'), ENT_QUOTES) ?>"
                               placeholder="Enter your 32-character API token"
                               required />
                        <p class="adsterra-description">
                            🔑 Get your API token from <a href="https://beta.publishers.adsterra.com/api-token" target="_blank">Adsterra API Token page</a>.
                            Generate a new token and paste it here.
                        </p>
                    </div>

                    <div class="adsterra-form-group">
                        <label class="adsterra-form-label" for="adsterra_domain">Select Domain</label>
                        <?php if (!empty($domains)) { ?>
                            <select id="adsterra_domain" class="adsterra-form-select" name="<?php print ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX; ?>_domain_id">
                                <option value="all" <?php echo (get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id') === 'all') ? 'selected' : ''; ?>>🌐 All Domains</option>
                                <?php
                                $selectedDomainID = get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id');

                                foreach ($domains as $domain) {
                                    $selectedDom = '';
                                    if (intval($selectedDomainID) === intval($domain['id'])) {
                                        $selectedDom = ' selected ';
                                    }

                                    echo '<option value="' . esc_attr($domain['id']) . '" ' . $selectedDom . '>' . esc_html($domain['title']) . '</option>' . PHP_EOL;
                                }
                                ?>
                            </select>
                            <p class="adsterra-description">
                                📊 Choose "All Domains" to view combined statistics, or select a specific domain
                            </p>
                        <?php } else { ?>
                            <p class="adsterra-description">
                                ⚠️ Enter a valid API token and save to view available domains
                            </p>
                        <?php } ?>
                    </div>

                    <div class="adsterra-submit-wrapper">
                        <button type="submit" class="adsterra-btn-primary">
                            💾 Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php
    }

    public function adsterra_dashboard_widget() {
        require_once ADSTERRA_DASHBOARD_PLUGIN_DIR . 'libs/class-api-client.php';

        $pluginSettings = [
            'enabled' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'token' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token'),
            'domain_id' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id'),
            'widget_filter_month' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        if (empty($pluginSettings['enabled']) || empty($pluginSettings['token'])) {
            echo '<h3>Plugin not active or token invalid, please enter into <a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wp-adsterra-dashboard%2Findex.php')) . '">Setting page</a> and enable it</h3>';
            return;
        }

        $adsterraAPIClient = new WPAdsterraDashboardAPIClient($pluginSettings['token'], $pluginSettings['domain_id']);

        $dateFilter = null;
        $errorMessage = null;

        if ($pluginSettings['widget_filter_month']) {
            $dateFilter = date('Y-m-d', $pluginSettings['widget_filter_month']);
            $monthActiveName = date('F', $pluginSettings['widget_filter_month']);
        } else {
            $monthActiveName = date('F');
        }

        // Create cache key based on settings
        $cache_key = 'adsterra_widget_cache_' . md5(serialize($pluginSettings));
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            // Use cached data
            extract($cached_data);
        } else {
            // Fetch fresh data

        // Get placements based on domain selection
        $placements = [];

        if ($pluginSettings['domain_id'] === 'all') {
            // Get all domains and their placements
            $domains = $adsterraAPIClient->getDomains();

            if (!empty($domains['code']) && in_array($domains['code'], [401, 403])) {
                $errorMessage = 'Adsterra Dashboard. API Token error Code: ' . $domains['code'] . ' Message: ' . $domains['message'];
            } else {
                foreach ($domains as $domain) {
                    $domainPlacements = $adsterraAPIClient->getPlacementsByDomainID($domain['id']);

                    if (!empty($domainPlacements) && !isset($domainPlacements['code'])) {
                        foreach ($domainPlacements as $placement) {
                            $placement['domain_id'] = $domain['id'];
                            $placement['domain_title'] = $domain['title'];
                            $placements[] = $placement;
                        }
                    }
                }
            }
        } else {
            $placements = $adsterraAPIClient->getPlacementsByDomainID($pluginSettings['domain_id']);

            if (!empty($placements['code']) && in_array($placements['code'], [401, 403])) {
                $errorMessage = 'Adsterra Dashboard. API Token error Code: ' . $placements['code'] . ' Message: ' . $placements['message'];

                $placements = [];
            }
        }

        $totalImpressions = 0;
        $totalClicks = 0;
        $totalCTR = 0;
        $totalCPM = 0;
        $totalRevenue = 0;

        $labels_X = [];
        $values_Y = [];

        $statParams = [];

        if (!empty($dateFilter)) {
            $statParams['start_date'] = date('Y-m-01', strtotime($dateFilter));
            $statParams['finish_date'] = date('Y-m-t', strtotime($dateFilter));
        } else {
            $statParams['start_date'] = date('Y-m-01');
            $statParams['finish_date'] = date('Y-m-t');
        }

        $period = new DatePeriod(
            new DateTime($statParams['start_date']),
            new DateInterval('P1D'),
            new DateTime($statParams['finish_date'])
        );

        foreach ($period as $key => $value) {
            $labels_X[date('j', strtotime($value->format('Y-m-d')))] = $value->format('d');
        }

        // Use individual placement calls for better compatibility
        foreach ($placements as $placementStats) {
            $placementID = $placementStats['id'];
            $placementTitle = $placementStats['title'];
            $domainID = $placementStats['domain_id'] ?? $pluginSettings['domain_id'];

            $statsSinglePlacement = $adsterraAPIClient->getStatsByPlacementID($domainID, $placementID, $statParams);

            if (!empty($statsSinglePlacement['code']) && in_array($statsSinglePlacement['code'], [401, 403, 500])) {
                $errorMessage = 'Adsterra Dashboard. API Error Code: ' . $statsSinglePlacement['code'] . ' Message: ' . ($statsSinglePlacement['message'] ?? 'Unknown error');
                break;
            }

            if (!empty($statsSinglePlacement['items'])) {
                foreach ($statsSinglePlacement['items'] as $statSinglePlacement) {
                    $day = date('j', strtotime($statSinglePlacement['date']));

                    if (!isset($values_Y[$placementTitle])) {
                        $values_Y[$placementTitle] = [];
                    }
                    if (!isset($values_Y[$placementTitle][$day])) {
                        $values_Y[$placementTitle][$day] = 0;
                    }
                    $values_Y[$placementTitle][$day] += floatval($statSinglePlacement['revenue'] ?? 0);

                    // Accumulate totals
                    $totalImpressions += intval($statSinglePlacement['impression'] ?? 0);
                    $totalClicks += intval($statSinglePlacement['clicks'] ?? 0);
                    $totalRevenue += floatval($statSinglePlacement['revenue'] ?? 0);
                }
            }
        }

        // Calculate proper weighted averages for CPM and CTR
        if ($totalImpressions > 0) {
            $totalCPM = ($totalRevenue / $totalImpressions) * 1000; // CPM = (Revenue / Impressions) * 1000
            $totalCTR = ($totalClicks / $totalImpressions) * 100; // CTR = (Clicks / Impressions) * 100
        }

            // Cache the data for 1 hour
            $cache_data = compact('placements', 'totalImpressions', 'totalClicks', 'totalCTR', 'totalCPM', 'totalRevenue', 'labels_X', 'values_Y', 'errorMessage');
            set_transient($cache_key, $cache_data, HOUR_IN_SECONDS);
        }

        foreach ($values_Y as $placementTitle => $dataPlacement) {
            foreach ($dataPlacement as $day => $data) {
                foreach ($labels_X as $kDay => $vDay) {
                    if (!isset($values_Y[$placementTitle][$kDay])) {
                        $values_Y[$placementTitle][$kDay] = 0;
                    }
                }
            }
        }
    ?>

        <div id="container-box">

            <?php if ($errorMessage) { ?>
                <div class="error notice">
                    <p><?php echo esc_html($errorMessage); ?></p>
                </div>
                <p>Please enter into <a href="<?php echo esc_url(get_admin_url(null, 'options-general.php?page=wp-adsterra-dashboard%2Findex.php')); ?>">Setting page</a> for resolve problem.</p>
            <?php } else { ?>

                <div id="containerChartjs">
                    <canvas id="adsterraStatsCanvas"></canvas>
                </div>

                <div class="adsterra-controls">
                    <table>
                        <tr>
                            <td style="width:30%; color: #666; font-weight: 600;">📅 Filter Month:</td>
                            <td style="width:50%;">
                                <select id="adsterra_dashboard_widget_filter_month">
                                    <?php
                                    for ($i = 0; $i <= 12; $i++) {

                                        $selectValue = date('F Y', strtotime("-$i month"));

                                        $selectedDom = '';
                                        if (
                                            (!$dateFilter && date('Y-m', strtotime($selectValue)) == date('Y-m')) ||
                                            ($dateFilter && date('Y-m', strtotime($dateFilter)) == date('Y-m', strtotime($selectValue)))
                                        ) {
                                            $selectedDom = ' selected ';
                                        }

                                        echo '<option value="' . esc_attr(strtotime($selectValue)) . '" ' . $selectedDom . '>' . esc_html($selectValue) . '</option>' . PHP_EOL;
                                    }
                                    ?>
                                </select>
                            </td>
                            <td style="width:20%; text-align: right;">
                                <button type="button" id="adsterra_refresh_cache" class="button button-secondary">
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Refresh
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="stats-grid">
                    <div class="small-box">
                        <h3>👁️ Impressions</h3>
                        <p><?php echo esc_html(number_format($totalImpressions, 0, '.', ',')); ?></p>
                    </div>
                    <div class="small-box">
                        <h3>🖱️ Clicks</h3>
                        <p><?php echo esc_html(number_format($totalClicks, 0, '.', ',')); ?></p>
                    </div>
                    <div class="small-box">
                        <h3>💰 CPM</h3>
                        <p>$<?php echo esc_html(number_format($totalCPM, 2, '.', ',')); ?></p>
                    </div>
                    <div class="small-box">
                        <h3>📊 CTR</h3>
                        <p><?php echo esc_html(number_format($totalCTR, 2, '.', ',')); ?>%</p>
                    </div>

                    <div class="small-box small-md-6">
                        <h3>💵 Grand Earnings</h3>
                        <p>$<?php echo esc_html(number_format($totalRevenue, 2, '.', ',')); ?></p>
                    </div>
                </div>
            <?php } ?>
        </div>

        <script>
            function adsterraRandomRGBAColor() {
                var o = Math.round,
                    r = Math.random,
                    s = 255;
                return 'rgba(' + o(r() * s) + ',' + o(r() * s) + ',' + o(r() * s) + ', 1)';
            }

            var ADSTERRA_LABELS_X = <?php echo wp_json_encode(array_values($labels_X)); ?>;

            var adsterraChartConfig = {
                type: 'line',
                data: {
                    labels: ADSTERRA_LABELS_X,
                    datasets: [
                        <?php foreach ($values_Y as $key => $value) { ?> {
                                label: <?php echo wp_json_encode(strtoupper($key)); ?>,
                                backgroundColor: adsterraRandomRGBAColor(),
                                borderColor: adsterraRandomRGBAColor(),
                                data: <?php echo wp_json_encode(array_values($values_Y[$key])); ?>,
                                fill: false,
                            },
                        <?php } ?>
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    title: {
                        display: false,
                        text: 'Adsterra Stats'
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var corporation = data.datasets[tooltipItem.datasetIndex].label;
                                var valor = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];

                                var total = 0;
                                for (var i = 0; i < data.datasets.length; i++)
                                    total += data.datasets[i].data[tooltipItem.index];

                                if (tooltipItem.datasetIndex != data.datasets.length - 1) {
                                    return corporation + " : $ " + valor.toFixed(3).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                                } else { // .. else, you display the dataset and the total, using an array
                                    return [corporation + " : $ " + valor.toFixed(3).replace(/(\d)(?=(\d{3})+\.)/g, '$1,'), "Total : $ " + total.toFixed(3)];
                                }
                            }
                        }
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    },
                    scales: {
                        xAxes: [{
                            display: true,
                            scaleLabel: {
                                display: true,
                                labelString: <?php echo wp_json_encode('Days of ' . $monthActiveName); ?>
                            }
                        }],
                        yAxes: [{
                            display: true,
                            scaleLabel: {
                                display: false,
                                labelString: 'Values'
                            }
                        }]
                    }
                }
            };

            document.addEventListener("DOMContentLoaded", function() {

                var adsterraCtx = document.getElementById('adsterraStatsCanvas').getContext('2d');
                var adsterraStatsCanvas = new Chart(adsterraCtx, adsterraChartConfig);
            });
        </script>
<?php
    }
}

new WPAdsterraDashboard();
?>
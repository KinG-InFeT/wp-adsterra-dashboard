<?php
/**
 * Uninstall script for WP Adsterra Dashboard
 *
 * This file is called when the plugin is uninstalled to clean up all data
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define the options prefix
define('ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX', 'wp_adsterra_dashboard_option');

// Delete all plugin options
delete_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled');
delete_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token');
delete_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id');
delete_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter');

// Clear any cached data (transients)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adsterra_api_%' OR option_name LIKE '_transient_timeout_adsterra_api_%'");

// Clear any scheduled hooks if they exist
wp_clear_scheduled_hook('adsterra_cleanup_cache');
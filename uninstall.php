<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wp_adsterra_dashboard_option_enabled');
delete_option('wp_adsterra_dashboard_option_token');
delete_option('wp_adsterra_dashboard_option_domain_id');
delete_option('wp_adsterra_dashboard_option_widget_month_filter');

// Cached stats are transients with a one-hour expiry: WordPress removes them on its own

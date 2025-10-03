=== WP Adsterra Dashboard ===
Contributors: Vincenzo Luongo
Tags: adsterra dashboard, adsterra stats, adsterra publishers dashboard
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure and optimized WP AdsTerra Dashboard for viewing statistics via API with enhanced performance and security features.

== Description ==

WP AdsTerra Dashboard allows you to view your Adsterra advertising statistics directly in your WordPress admin dashboard. This plugin provides a convenient widget that displays your daily earnings, impressions, clicks, CPM, and CTR data with beautiful charts.

**Key Features:**
* Secure AJAX calls with CSRF protection
* Optimized API performance with caching
* Real-time statistics dashboard widget
* Monthly data filtering
* Interactive charts with Chart.js
* Robust error handling and validation
* WordPress security best practices compliance

**Version 2.0.0 Highlights:**
* Complete security overhaul with XSS and CSRF protection
* Performance optimization with API call reduction and caching
* Enhanced mathematical accuracy for CPM/CTR calculations
* Improved error handling and user experience

== Installation ==

1. Upload `wp-adsterra-dashboard` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Edit the plugin settings by clicking "Adsterra Settings" on the settings navbar

== Frequently Asked Questions ==

= This is official plugin? =

No

= Is there a premium version available? =

There is currently no premium version available.

= Where can I get the api token? =

Setup an free account and get your credential from [Adsterra - API Documentations](https://api3.adsterratools.com/docs/publishers "Adsterra Docs") 


== Screenshots ==

1. Adsterra API Documentations Page 
2. Settings Page
3. Widget

== Changelog ==

= 2.0.0 =
* MAJOR UPDATE - Complete security overhaul and API optimization
* SECURITY: Added CSRF protection with nonce verification for AJAX calls
* SECURITY: Fixed XSS vulnerabilities with proper output escaping (esc_html, esc_attr, wp_json_encode)
* SECURITY: Added input sanitization for all user inputs (sanitize_text_field)
* SECURITY: Enhanced API token validation and domain ID validation
* NEW FEATURE: Added "All Domains" option to view combined statistics from all domains
* NEW FEATURE: Implemented dashboard widget data caching (1 hour) for improved performance
* NEW FEATURE: Added manual refresh button to update cached dashboard data
* BUG FIX: Fixed API groupBy parameter - removed invalid values, now using correct API parameters
* BUG FIX: Corrected domain selection logic in settings page
* BUG FIX: Fixed API client placement parameter not being used
* BUG FIX: Fixed unclosed HTML tags
* BUG FIX: Fixed widget layout overflow issues with proper responsive design
* PERFORMANCE: Optimized API calls - individual placement calls for better compatibility
* PERFORMANCE: Implemented widget data caching with transients (1 hour TTL)
* PERFORMANCE: Added AJAX-based cache refresh functionality
* UI/UX: Complete redesign of settings page with modern gradient design
* UI/UX: Enhanced dashboard widget with improved visual design and CSS Grid layout
* UI/UX: Added modern toggle switch for enable/disable setting
* UI/UX: Improved form inputs with focus states and animations
* UI/UX: Responsive 2-column grid layout for statistics boxes
* UI/UX: Added hover effects and smooth transitions
* UI/UX: Compact and optimized widget size to fit dashboard properly
* IMPROVEMENT: Enhanced error handling with proper logging
* IMPROVEMENT: Fixed mathematical calculations for CPM and CTR (weighted averages)
* IMPROVEMENT: Added robust field mapping for different API response structures
* IMPROVEMENT: Updated JavaScript with better error handling and user feedback
* IMPROVEMENT: Added uninstall.php for proper cleanup
* IMPROVEMENT: Code cleanup - removed commented code and improved structure
* API: Enhanced compatibility with Adsterra API documentation
* API: Fixed API parameters to match official documentation (removed invalid groupBy values)
* COMPATIBILITY: Maintained backward compatibility while adding new features

= 1.3.0 =
* Update API token auth and minor FIX

= 1.2.4 =
* Support for Wordpress 6.x added

= 1.2.3 =
* Support for Wordpress 5.8 added

= 1.2.2 =
* Support for Wordpress 5.8 added

= 1.2.1 =
* Support for Wordpress 5.7 added

= 1.2.0 =
* Minor Bug Fix and added support for Wordpress 5.6

= 1.1.0 =
* Minor Bug Fix

= 1.0.0 =
* Public release
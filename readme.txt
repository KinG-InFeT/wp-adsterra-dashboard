=== WP Adsterra Dashboard ===
Contributors: vluongo
Tags: adsterra, earnings, statistics, dashboard widget, publishers
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See your Adsterra earnings on the WordPress dashboard: a daily chart for each ad placement, plus impressions, clicks, CPM, CTR and earnings.

== Description ==

Check how much your Adsterra ads earn without leaving WordPress. The plugin adds a widget to your dashboard with the figures of your Adsterra publisher account.

= How it works =

1. Create an API token in your Adsterra publisher account.
2. Paste it in Settings > Adsterra Settings and enable the widget.
3. Open the dashboard: the widget shows the current month.

= What the widget shows =

* A chart with the daily earnings of each ad placement.
* The month totals: impressions, clicks, CPM, CTR and earnings.
* The current month or any of the previous 12, picked from a menu.
* All your sites together, or a single site chosen in the settings.

= Features =

* Figures are updated every hour, or right away with the Refresh button.
* Only administrators see the widget.
* Available in English and Italian.

This is not an official Adsterra plugin.

= External service =

The plugin reads your domains, placements and statistics from the Adsterra Publishers API (`api3.adsterratools.com`), sending the API token you enter in the settings. Requests are made only in the admin area, when an administrator opens the dashboard or the plugin settings. No data about your visitors is sent. See the Adsterra [terms](https://adsterra.com/terms-and-conditions/) and [privacy policy](https://adsterra.com/privacy-policy/).

= Credits =

The chart is drawn with [Chart.js](https://www.chartjs.org/) (MIT license), bundled with the plugin.

== Installation ==

1. Install the plugin from Plugins > Add New, or upload the `wp-adsterra-dashboard` folder to `/wp-content/plugins/`.
2. Activate it.
3. Go to Settings > Adsterra Settings, enable the widget, paste your API token and save.
4. Choose a domain, or keep "All Domains", and save again.

== Frequently Asked Questions ==

= Where do I get the API token? =

In your Adsterra publisher account, on the API page: generate a token and paste it in the plugin settings.

= The widget shows "Token expired" or another error. What should I do? =

The message comes from Adsterra. For an expired or invalid token, generate a new one and save it in the settings. Temporary errors disappear on their own: errors are never cached, so the next dashboard load tries again.

= How up to date is the data? =

Statistics are cached for one hour. Click Refresh in the widget to reload the month you are viewing.

= Who can see the widget? =

Only users who can manage the site options, normally administrators.

== Screenshots ==

1. The API token page in the Adsterra publisher panel.
2. Settings page.
3. Dashboard widget: daily earnings per placement, month filter and totals.

== Changelog ==

= 4.0.0 =
* Tested up to WordPress 7.1.
* Fix: an Adsterra API error other than 401/403 (rate limit, server error, removed domain) crashed the whole dashboard.
* Fix: the chart dropped the last day of the month and moved values to the wrong day when a placement had no data on some days.
* Fix: the widget with earnings was shown to every user with dashboard access, not only administrators.
* Fix: saving an invalid token or domain erased the saved one.
* Fix: an API error stayed on screen for an hour after the problem was solved, and Refresh did not reload the statistics.
* Fix: the month filter could repeat a month and skip another on the 29th, 30th and 31st.
* Fix: "translation loading triggered too early" notice on WordPress 6.7+ for sites with the plugin translated.
* The widget now needs two API calls instead of one per domain and per placement, and shows the error message returned by Adsterra.
* Chart: each placement has its own line; placements with the same name on different sites show the site name.
* New: Italian translation.
* Settings are now deleted with the plugin: the uninstall file was missing from the released package.
* The settings page moved to `options-general.php?page=wp-adsterra-dashboard`.

= 3.0.0 =
* Chart.js updated from 2.9.3 to 4.4.7.
* Capability checks on the widget AJAX actions; dates follow the site timezone.
* Settings page styles moved to the plugin stylesheet. Tested up to WordPress 7.0; requires PHP 8.0.

= 2.0.0 =
* New: "All Domains" option, one-hour cache for the widget and a Refresh button.
* Nonce checks on AJAX requests and escaped output.
* Redesigned settings page and widget.

= 1.3.0 =
* The API token is sent in a request header, as the Adsterra API now requires.

= 1.2.4 =
* WordPress 6.x support.

= 1.2.3 =
* WordPress 5.8 support.

= 1.2.2 =
* WordPress 5.8 support.

= 1.2.1 =
* WordPress 5.7 support.

= 1.2.0 =
* WordPress 5.6 support and bug fixes.

= 1.1.0 =
* Bug fixes.

= 1.0.0 =
* First release.

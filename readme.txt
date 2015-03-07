=== GCal Days ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: calendar, google, gcal, shortcode, days since, days until, coffee2code
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 3.6
Tested up to: 4.1
Stable tag: 1.1.1

Shortcode and functions to query your Google Calendar for the number of days since or until the most recent event matching your search criteria.

== Description ==

This plugin provides a shortcode and a set of functions to return the number of days since the most recent past event in your Google Calendar matching specified search terms. The shortcode can also be used to return the number of days until the closest upcoming event matching specified search terms.

= The Shortcode =

`[gcal-days search="" type="" id=""]`

The shortcode name is `gcal-days`. It has three attributes, but only one of which is absolutely necessary:

* `search`: (Required.) The search term.
* `type`: (Optional.) The direction in time to search. Defaults to "since", which searches past events. "until" will search future events.
* `id`: (Conditionally optional.) The ID of the calendar to search. You can discover the IDs of calendars via the plugin's settings page. This shortcode attribute can only be optionally omitted from shortcodes if you define a default calendar in the plugin's settings.

The shortcode outputs a number representing the number of days until a matching event. If no event is found, a -1 will be displayed.

Examples: `[gcal-days search="dentist"]`, `[gcal-days type="until" search="vacation" id="test_lkjsadf987asdf24@group.calendar.google.com"]`

Links: [Plugin Homepage](http://coffee2code.com/wp-plugins/gcal-days/) | [Plugin Directory Page](https://wordpress.org/plugins/gcal-days/) | [Author Homepage](http://coffee2code.com)


== Installation ==

1. Unzip `gcal-days.zip` inside the `/wp-content/plugins/` directory for your site (or install via the built-in WordPress plugin installer).
1. Activate the plugin through the 'Plugins' admin menu in WordPress.
1. Visit the plugin's setting page, 'Settings' -> 'GCal Days', and follow the link to obtain an authorization code from Google that permits the plugin access to your Google Calendar data.
1. Use the provided shortcode or functions, as per instructions.


== Frequently Asked Questions ==

= Does that mean I am granting you (the plugin author) access to my Google Calendar data? =

No. Google's API allows for an app (such as this plugin) to be granted specific access (see next question). The access token is requested by the plugin and stored in your database. The data is only communicated back and forth between your site and Google via HTTPS.

= What sort of access from Google is the plugin requesting? =

The plugin is only requesting read-only access to Google Calendars. As such, the plugin will not be able to make any changes to your calendars, nor will it be able to access data associated with other Google services.

= Why isn't a calendar change I just made in Google Calendar being recognized by the plugin? =

To avoid the expense of making network connections to Google for every page load, information is cached for a short period of time. You may have to wait a few minutes after calendar changes before expecting to see those changes reflected in the plugin.


== Functions ==

The plugin provides two functions for use in your theme templates, functions.php, or in plugins.

= Functions =

* `<?php function gcal_days_since( $search, $calendar_id = '' ) ?>`
* `<?php function gcal_days_until( $search, $calendar_id = '' ) ?>`

= Arguments =

* `$search` (string)
Required. The word or phrase to search for

* `$calendar_id` (string)
Optional. The ID for the Google Calendar. Check the plugin's settings page for calendar IDs. This argument is only optional if you have defined a default calendar via the plugin's settings.

= Return Value =

An integer value of the number of days since/until the matching event. -1 is returned if no event was found or an error was encountered.

= Examples =

* `<?php // Days until next dentist appointment
$days_until = gcal_days_until( 'dentist' );
?>`

* `<?php
// Get the days since my last day off
$days_since = gcal_days_since( 'day off' );
// Echo a message using that number
if ( -1 == $days_since ) {
  echo "You've never had a day off?! Take one soon!";
} else {
  printf( _n( 'Your last day off was %d day ago.', 'Your last day off was $d days ago.', $days_since ), $days_since );
}
?>`


== Screenshots ==

1. A screenshot of the plugin's admin settings page.


== Changelog ==

= 1.1.1 (2015-03-06) =
* Declare `shortcode()` to be static
* Add support for localization
* Note compatibility through WP 4.1+
* Change documentation links to wp.org to be https
* Update copyright date (2015)
* Add plugin icon

= 1.1 =
* Apply do_shortcode to widget_text so the shortcode works in text widgets
* Add actual documentation for the shortcode
* Add screenshot
* Add banner
* Add another FAQ item

= 1.0 =
* Initial release


== Upgrade Notice ==

= 1.1.1 =
Trivial update: added support for localization; noted compatibility through WP 4.1+; added plugin icon

= 1.1 =
Recommended minor update: allow shortcode to work in text widgets; added documentation for shortcode.

= 1.0 =
Initial release!

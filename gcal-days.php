<?php
/**
 * Plugin Name: GCal Days
 * Version:     1.0
 * Plugin URI:  http://coffee2code.com/wp-plugins/gcal-days/
 * Author:      Scott Reilly
 * Author URI:  http://coffee2code.com/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Description: Shortcode and functions to query your Google Calendar for the number of days since or until the most recent event matching your search criteria.
 *
 * Compatible with WordPress 3.6 through 3.8+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: http://wordpress.org/plugins/gcal-days/
 *
 * @package GCal_Days
 * @author  Scott Reilly
 * @version 1.0
 */

/*
TODO:
	* Support for multiple GCal accounts?
	* Merge calendar listing and default calendar id setting, making a radiobutton list of calendars to choose as the default
	* Improve error handling
*/

/*
	Copyright (c) 2014 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_GCalDays' ) ) :

class c2c_GCalDays {

	private static $shortcode = 'gcal-days';

	/**
	 * Returns version of the plugin.
	 *
	 * @since 1.0
	 */
	public static function version() {
		return '1.0';
	}

	/**
	 * Hooks actions and filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Performs initializations on the 'init' action.
	 *
	 * @since 1.0
	 */
	public static function do_init() {
		require_once( dirname( __FILE__ ) . '/gcal-days.settings.php' );

		add_shortcode( self::$shortcode, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Loads the Google API if it hasn't already been loaded.
	 *
	 * @since 1.0
	 */
	public static function load_google_api() {
		if ( ! class_exists( 'c2c_GCalDaysGoogle' ) ) {
			require_once( dirname( __FILE__ ) . '/gcal-days.google.php' );
		}
	}

	/**
	 * Handles the shortcode.
	 *
	 * @since 1.0
	 *
	 * @param  array  $atts    The shortcode attributes parsed into an array.
	 * @param  string $content The content between opening and closing shortcode tags.
	 * @return string
	 */
	public function shortcode( $atts, $content = null ) {
		self::load_google_api();

		$defaults = array(
			'id'     => '',
			'search' => '',
			'type'   => 'since',
		);
		$a = shortcode_atts( $defaults, $atts );

		// Validate attributes
		$a['type'] = in_array( $a['type'], array( 'since', 'until' ) ) ? $a['type'] : 'since';

		if ( empty( $a['search'] ) ) {
			return __( '(no search defined)' );
		}

		if ( 'since' == $a['type'] ) {
			$days = c2c_GCalDaysGoogle::days_since( $a['search'], $a['id'] );
		} else {
			$days = c2c_GCalDaysGoogle::days_until( $a['search'], $a['id'] );
		}

		return $days;
	}

} // end c2c_GCalDays

c2c_GCalDays::init();

/**
 * Returns the number of days since the most recent event matching search criteria.
 *
 * @since 1.0
 *
 * @param  string     $search
 * @param  string     $calendar_id Optional. The ID of the Google Calendar calendar to search. If not defined, uses the default configured via settings.
 * @return int|string The number of days since the matching calendar event. -1 if there is no match.
 */
function gcal_days_since( $search, $calendar_id = '' ) {
	c2c_GCalDays::load_google_api();

	return c2c_GCalDaysGoogle::days_since( $search, $calendar_id );
}

/**
 * Returns the number of days until the most recent event matching search criteria.
 *
 * @since 1.0
 *
 * @param  string     $search
 * @param  string     $calendar_id Optional. The ID of the Google Calendar calendar to search. If not defined, uses the default configured via settings.
 * @return int|string The number of days until the matching calendar event. -1 if there is no match.
 */
function gcal_days_until( $search, $calendar_id = '' ) {
	c2c_GCalDays::load_google_api();

	return c2c_GCalDaysGoogle::days_until( $search, $calendar_id );
}

endif; // end if !class_exists()

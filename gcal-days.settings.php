<?php
/**
 *
 * This file encapsulates all the code related to storing, retrieving,
 * managing, and interacting with settings for the plugin.
 *
 */

if ( ! class_exists( 'c2c_GCalDaysSettings' ) ) :

class c2c_GCalDaysSettings {
	/**
	 * The name of the plugin's setting.
	 *
	 * All plugin settings are saved as array elements of this single setting.
	 *
	 * @var array
	 */
	public static  $admin_options_name = 'c2c_gcal_days';

	/**
	 * The WP name for the plugin's settings page.
	 *
	 * @var string
	 */
	public static  $settings_page      = '';

	/**
	 * The name of this file.
	 *
	 * @var string
	 */
	public static  $plugin_file        = __FILE__;

	/**
	 * The individual settings and their default values.
	 *
	 * @var array
	 */
	private static $options_default    = array( 'code' => '', 'default_calendar_id' => '', 'refresh_token' => '', 'note' => '' );

	/**
	 * The memoized settings.
	 *
	 * @var array
	 */
	private static $options            = null;

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0
	 */
	public static function init() {
		add_action( 'admin_init',     array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu',     array( __CLASS__, 'admin_menu' ) );
	}

	/**
	 * Creates the admin menu link and registers the plugin action link.
	 *
	 * @since 1.0
	 */
	public static function admin_menu() {
		add_filter( 'plugin_action_links_gcal-days/gcal-days.php', array( __CLASS__, 'plugin_action_links' ) );
		self::$settings_page = add_options_page( 'Settings', 'GCal Days', 'manage_options', __CLASS__, array( __CLASS__, 'settings_page' ) );
		add_action( 'admin_init', array( 'c2c_GCalDays', 'load_google_api' ) );
	}

	/**
	 * Adds a 'Settings' link to the plugin action links.
	 *
	 * @since 1.0
	 *
	 * @param  array $action_links Existing action links
	 * @return array Links associated with a plugin on the admin Plugins page
	 */
	public static function plugin_action_links( $action_links ) {
		$settings_link = '<a href="' . self::settings_page_url() . '">' . __( 'Settings', 'gcal-days' ) . '</a>';
		array_unshift( $action_links, $settings_link );
		return $action_links;
	}

	/**
	 * Returns the link to the plugin's settings page.
	 *
	 * @since 1.0
	 *
	 * @return string URL to settings page
	 */
	public static function settings_page_url() {
		return menu_page_url( __CLASS__, false );
	}

	/**
	 * Outputs the plugin's settings page.
	 *
	 * @since 1.0
	 */
	public static function settings_page() {
		echo "<div class='wrap'>\n";
		echo '<h2>' . __( 'GCal Days', 'gcal-days' ) . '</h2>';

		echo '<p>';
		_e( 'A plugin to provide a shortcode and functions to query your Google Calendar for the number of days since or until the most recent event matching your search criteria.', 'gcal-days' );
		echo '</p>';

		echo "<form action='" . admin_url( 'options.php' ) . "' method='post' class='c2c-form'>\n";

		settings_fields( self::$admin_options_name );
		do_settings_sections( self::$plugin_file );

		self::show_test();

		echo '<p class="submit">';
		echo '<input type="submit" name="Submit" class="button-primary" value="' . esc_attr( 'Save Changes' ) . '" />';
		echo ' ';
		echo '<input type="submit" name="Reset" class="button-secondary" value="' . esc_attr( 'Reset' ) . '" />';
		echo '</p>';
		echo "</form>\n";
		echo "</div>\n";

		if ( isset( $_GET['show_tokens'] ) && '1' == $_GET['show_tokens'] ) {
			echo '<div class="wrap">';
			echo '<hr />';
			echo '<h3>' . __( 'Tokens', 'gcal-days' ) . '</h3>';
			echo '<p>' . sprintf( __( 'Access token: %s', 'gcal-days' ), c2c_GCalDaysGoogle::get_access_token() ) . '</p>';
			echo '<p>' . sprintf( __( 'Refresh token: %s', 'gcal-days' ), c2c_GCalDaysGoogle::get_refresh_token() ) . '</p>';
			echo '</div>';
		}

		self::show_calendars();
	}

	/**
	 * Registers the plugin's settings.
	 *
	 * @since 1.0
	 */
	public static function register_settings() {
		register_setting( self::$admin_options_name, self::$admin_options_name, array( __CLASS__, 'validate_options' ) );
		add_settings_section( 'default', '', '__return_false', self::$plugin_file );
		add_settings_field( 'code', __( 'Google API authorization code', 'gcal-days' ),
			array( __CLASS__, 'display_option' ),
			self::$plugin_file,
			'default',
			array( 'label_for' => 'code' )
		);
		add_settings_field( 'default_calendar_id', __( 'Default calendar ID', 'gcal-days' ),
			array( __CLASS__, 'display_option' ),
			self::$plugin_file,
			'default',
			array( 'label_for' => 'default_calendar_id' )
		);
		add_settings_field( 'note', __( 'Note to self', 'gcal-days' ),
			array( __CLASS__, 'display_option' ),
			self::$plugin_file,
			'default',
			array( 'label_for' => 'note' )
		);
	}

	/**
	 * Resets the plugin's settings.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function reset_options() {
		delete_transient( c2c_GCalDaysGoogle::access_token_name );
		c2c_GCalDaysGoogle::cache_delete( 'get_calendars' ); // TODO: This should be done inside c2c_GCalDaysGoogle
		self::$options = self::get_options( '', true );
		return self::$options;
	}

	/**
	 * Validates and sanitizes settings before they are saved.
	 *
	 * @since 1.0
	 *
	 * @param  array $new_options The settings submitted by the user.
	 * @return array The settings to actually get saved.
	 */
	public static function validate_options( $new_options ) {
		$old_options = self::get_options();

		if ( isset( $_POST['Reset'] ) ) {
			$options = self::reset_options();
			add_settings_error( 'general', 'settings_reset', __( 'The settings have been reset.', 'gcal-days' ), 'updated' );
			return $options;
		}

		if ( ( ! isset( $new_options['code'] ) || empty( $new_options['code'] ) ) ) {
			add_settings_error( 'code', 'gcal-days-no-code', __( 'Please obtain an authentication code from Google via the link under the input field.', 'gcal-days' ), 'error' );
			$options['code'] = '';
		} else {
			$options['code'] = sanitize_text_field( $new_options['code'] );

			// TODO: Here we can immediately attempt to get tokens. If it fails, user may have given incorrect code or needs to re-auth.
		}

		if ( isset( $new_options['note'] ) && ! empty( $new_options['note'] ) ) {
			$options['note'] = sanitize_text_field( $new_options['note'] );
		}

		if ( isset( $new_options['default_calendar_id'] ) && ! empty( $new_options['default_calendar_id'] ) ) {
			$options['default_calendar_id'] = sanitize_text_field( $new_options['default_calendar_id'] );
		}

		// Always carry over refresh token and never accept it as a submitted value.
		$options['refresh_token'] = isset( $old_options['refresh_token'] ) ? $old_options['refresh_token'] : '';

		// Handle test
		if ( ! empty( $options['code'] ) && isset( $new_options['test_search'] ) && ! empty( $new_options['test_search' ] ) ) {
			$type        = ( isset( $new_options['test_type'] ) && 'until' == $new_options['test_type'] ) ? 'until' : 'since';
			$calendar_id = isset( $new_options['test_calendar_id'] ) ?
				$new_options['test_calendar_id'] :
				( isset( $new_options['default_calendar_id'] ) ? $new_options['default_calendar_id'] : '' );

			if ( 'since' == $type ) {
				$days = c2c_GCalDaysGoogle::days_since( $new_options['test_search'], $calendar_id );
				$msg  = __( 'Test: There have been %s days since the most recent past event in your calendar matching "%s".', 'gcal-days' );
			} else {
				$days = c2c_GCalDaysGoogle::days_until( $new_options['test_search'], $calendar_id );
				$msg  = __( 'Test: There are %s days until the closest upcoming event in your calendar matching "%s".', 'gcal-days' );
			}

			add_settings_error( 'general', 'settings_test_api', sprintf( $msg, $days, sanitize_text_field( $new_options['test_search'] ) ), 'updated' );
		}

		return $options;
	}

	/**
	 * Returns all of the plugin's settings, or just the value for a single setting.
	 *
	 * Can also return the default value for all settings or a single setting.
	 *
	 * @since 1.0
	 *
	 * @param  string $key                 The setting to get the value for. Returns all settings and values if blank.
	 * @param  bool   $default_values_only Should the default value (and not the actual value) be returned?
	 * @return string|array
	 */
	public static function get_options( $key = '', $default_values_only = false ) {
		if ( $default_values_only ) {
			if ( empty( $key ) ) {
				return self::$options_default;
			} else {
				return isset( self::$options_default[ $key ] ) ? self::$options_default[ $key ] : '';
			}
		}

		if ( empty( self::$options ) ) {
			self::$options = get_option( self::$admin_options_name );
		}

		if ( empty( $key ) ) {
			$val = self::$options;
		} else {
			$val = isset( self::$options[ $key ] ) ? self::$options[ $key ] : '';
		}

		return $val;
	}

	/**
	 * Updates the value for a setting.
	 *
	 * @since 1.0
	 *
	 * @param  string $key The name of the setting to update.
	 * @param  string $val The new value for the setting.
	 * @return string The value.
	 */
	public static function update_option( $key, $val ) {
		$options = self::get_options();

		$options[ $key ] = $val;

		self::$options = $options;
		update_option( self::$admin_options_name, self::$options );

		return $val;
	}

	/**
	 * Displays the markup for an individual setting.
	 *
	 * @since 1.0
	 *
	 * @param string $opt The name of the setting to display.
	 */
	public static function display_option( $opt ) {
		$options = self::get_options();
		$field = $opt['label_for'];
		$val   = isset( $options[ $field ] ) ? $options[ $field ] : '';
		if ( 'code' == $field ) {
			echo '<input type="text" name="' . self::$admin_options_name . '[code]" id="c2c_gds_code" value="' . esc_attr( $val ) . '" size="70" />';
			echo '<p class="description">';
// TODO: Maybe change message to reflect that the auth needs to be done, or that it was already successfully done
			_e( 'In order for this plugin to access information from Google Calendar, you must first grant it permission from Google. Click the following link to do so; the plugin is requesting read-only access to your Google Calendar data:', 'gcal-days' );
			echo ' ' . sprintf( '<a href="%s" target="_blank">' . __( 'Authorize with Google', 'gcal-days' ) . '</a>.', c2c_GCalDaysGoogle::get_oauth_url() );
			echo '</p>';
		} elseif ( 'default_calendar_id' == $field ) {
			echo '<input type="text" name="' . self::$admin_options_name . '[default_calendar_id]" id="c2c_gds_default_calendar_id" value="' . esc_attr( $val ) . '" size="70" />';
			echo '<p class="description">' . __( "Optionally supply the ID of a calendar to be used as the default for shortcodes and functions that don't explicity specify a calendar.", 'gcal-days' ) . '</p>';
		} elseif ( 'note' == $field ) {
			echo '<input type="text" name="' . self::$admin_options_name . '[note]" id="c2c_gds_note" value="' . esc_attr( $val ) . '" size="70" />';
			echo '<p class="description">' . __( 'This is simply a note to yourself and is not used by the plugin. It is recommended that you use it to record which GMail account you have authorized this plugin to have access to.', 'gcal-days' ) . '</p>';
		}
	}

	/**
	 * Outputs a listing of the user's Google Calendars.
	 *
	 * @since 1.0
	 */
	public static function show_calendars() {
		$options = self::get_options();

		// Don't show anything if the authorization code for Google hasn't been provided yet.
		if ( ! isset( $options['code'] ) || empty( $options['code'] ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<hr />';
		echo '<h3>Your Calendars</h3>';
		$calendars = c2c_GCalDaysGoogle::get_calendars();
		if ( is_string( $calendars ) ) {
			echo '<p>' . $calendars . '</p>';
			if ( false !== strpos( $calendars, 'Invalid Credentials' ) ) {
				echo '<p><strong>' .
				__( 'It looks like there is a problem with the Google API authorization code. Please re-request a new authorization from Google using the link under the input field above.', 'gcal-days' ) .
				'</strong></p>';
			}
		} else {
			echo '<p>';
			_e( 'Note the ID values as that will be what you need to supply as the "id" attribute for the shortcode or the $calendar_id argument for functions (unless you assign one as the default above).', 'gcal-days' );
			echo '</p>';
			echo '<table><tr><th>' . __( 'ID', 'gcal-days' ) . '</th><th>' . __( 'Description', 'gcal-days' ) . '</th></tr>';
			foreach ( $calendars as $cal ) {
				echo '<tr><td>' . sanitize_text_field( $cal->id ) . '</td><td>' . sanitize_text_field( $cal->summary ) . '</td></tr>';
			}
			echo '</table>';
		}

		echo '</div>';
	}

	/**
	 * Outputs test fields.
	 *
	 * @since 1.0
	 */
	public static function show_test() {
		// Don't show the test if the authorization code hasn't been supplied.
		if ( ! c2c_GCalDaysGoogle::get_code() ) {
			return;
		}

		echo '<hr />';

		echo '<table class="form-table">';
		echo '<h3>Test the API</h3>';
		echo '<p>' . __( 'Optionally use the following form fields to test the API.', 'gcal-days' ) . '</p>';

		echo '<tr valign="top">';
		echo '<th scope="row"><label for="' . self::$admin_options_name . '[test_type]">' . __( 'Type', 'gcal-days' ) . '</label></th>';
		echo '<td><select name="' . self::$admin_options_name . '[test_type]" id="c2c_gds_test_type">';
		echo '<option value="since" selected="selected">' . __( 'since', 'gcal-days' ) . '</option>';
		echo '<option value="until">' . __( 'until', 'gcal-days' ) . '</option>';
		echo '</select></td>';
		echo '</tr>';

		echo '<tr valign="top">';
		echo '<th scope="row"><label for="' . self::$admin_options_name . '[test_search]">' . __( 'Search', 'gcal-days' ) . '</label></th>';
		echo '<td><input type="text" name="' . self::$admin_options_name . '[test_search]" id="c2c_gds_test_search" value="" size="70" /></td>';
		echo '</tr>';

		echo '<tr valign="top">';
		echo '<th scope="row"><label for="' . self::$admin_options_name . '[test_calendar_id]">' . __( 'Calendar ID', 'gcal-days' ) . '</label></th>';
		echo '<td><input type="text" name="' . self::$admin_options_name . '[test_calendar_id]" id="c2c_gds_test_calendar_id" value="" size="70" />';
		echo '<p class="description">' . __( 'Leave blank to use the default calendar defined above.', 'gcal-days' ) . '</p></td>';
		echo '</tr>';

		echo '</table>';
	}

}

c2c_GCalDaysSettings::init();

endif;

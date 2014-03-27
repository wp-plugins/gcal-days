<?php
/**
 *
 * This file encapsulates all the code related to storing, retrieving,
 * managing, and interacting with Google.
 *
 * TODO:
 *  * Detect if authorization code changes (maybe user re-requests it?)
 */

if ( ! class_exists( 'c2c_GCalDaysGoogle' ) ) :

class c2c_GCalDaysGoogle {

	/* Google API */
	const client_id         = '643509057871-3299qrgisr4cfs87gkn2383ir6bk1opi.apps.googleusercontent.com';
	const client_secret     = 'NJ59fXRJ7LZ5ui8xtt3DQno2';
	const redirect_uri      = 'urn:ietf:wg:oauth:2.0:oob';
	const scope             = 'https://www.googleapis.com/auth/calendar.readonly';

	const access_token_name = 'c2c_gcal_access_token';


	/****
	 * GOOGLE OAUTH2
	 ****/


	/**
	 * Returns the Google OAuth2 URL used to request access for the plugin to
	 * the user's Google Calendar data.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_oauth_url() {
		return "https://accounts.google.com/o/oauth2/auth?" . http_build_query( array(
			'response_type' => 'code',
			'redirect_uri'  => self::redirect_uri,
			'client_id'     => self::client_id,
			'scope'         => self::scope,
			'access_type'   => 'offline',
		) );
	}

	/**
	 * Returns the authorization code obtained by the user from Google, which
	 * permits access for the plugin to the user's Google Calendar data.
	 *
	 * Stored as a plugin setting since the user must follow the provided link
	 * to grant the plugin access and copy-and-paste the code Google provides.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_code() {
		return c2c_GCalDaysSettings::get_options( 'code' );
	}

	/**
	 * Returns the access token, refreshing it if necessary.
	 *
	 * The access token is necessary for all API requests. It has a short
	 * lifespan, so it may need to be refreshed.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_access_token() {
		$access_token = get_transient( self::access_token_name );

		if ( empty( $access_token ) ) {
			$access_token = self::refresh_token();
		}

		return $access_token;
	}

	/**
	 * Obtains access and refresh tokens from Google, using the authorization
	 * code provided by user after granting access via Google.
	 *
	 * This should only need to be performed once, at initial set up. Once doing
	 * so, a refresh token will be saved and stored as an option. Subsequent
	 * token updates would invoke refresh_token.
	 *
	 * @since 1.0
	 *
	 * @return string The access token.
	 */
	public static function get_tokens() {
		// TODO: If already have the refresh token, do refresh instead? Or do refresh and
		// if an error occurred, continue with this. Add $force arg to force getting new
		// tokens?

		$response = wp_remote_post( 'https://accounts.google.com/o/oauth2/token', array(
			'body' => array(
				'code'          => self::get_code(),
				'client_id'     => self::client_id,
				'client_secret' => self::client_secret,
				'redirect_uri'  => self::redirect_uri,
				'grant_type'    => 'authorization_code',
			),
		) );

		// Handle errors
		if ( self::is_api_error( $response ) ) {
			return self::get_api_error( $response );
		}

		// Response contains: access_token, refresh_token, expires_in, token_type

		$body = json_decode( $response['body'] );

		c2c_GCalDaysSettings::update_option( 'refresh_token', $body->refresh_token );
		set_transient( self::access_token_name, $body->access_token, 3600 );

		return $body->access_token;
	}

	/**
	 * Returns the refresh token.
	 *
	 * The refresh token is long-lived and necessary to obtain the short-lived,
	 * but directly used, access token.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_refresh_token() {
		return c2c_GCalDaysSettings::get_options( 'refresh_token' );
	}

	/**
	 * Refreshes the access token, if necessary.
	 *
	 * @since 1.0
	 *
	 * @return string The access token.
	 */
	public static function refresh_token() {
		$refresh_token = self::get_refresh_token();

		if ( empty( $refresh_token ) ) {
			$code = self::get_code();
			if ( empty( $code ) ) {
				return __( 'No Google API authorization code provided.' );
			} else {
				return self::get_tokens();
			}

			// TODO: Properly handle error. This should re-request authorization and refresh tokens
//			die( 'No refresh token has been retrieved.' );
//			return;
		}

		$response = wp_remote_post( 'https://accounts.google.com/o/oauth2/token', array(
			'body' => array(
				'refresh_token' => $refresh_token,
				'client_id'     => self::client_id,
				'client_secret' => self::client_secret,
				'grant_type'    => 'refresh_token',
			),
		) );

		if ( self::is_api_error( $response ) ) {
			return self::get_api_error( $response );
		}

		// Response contains: access_token, expires_in, token_type

		$body = json_decode( $response['body'] );

		set_transient( self::access_token_name, $body->access_token, 3600 );

		return $body->access_token;
	}


	/****
	 * CACHE
	 ****/


	/**
	 * Obtains the value cached using the given key.
	 *
	 * @param  string $key The cache key name.
	 * @return mixed|null  The cached value, or null.
	 */
	public static function cache_get( $key ) {
		return get_transient( 'c2c_gcal_days_cache_' . sanitize_key( $key ) );
	}

	/**
	 * Caches a value under the given key.
	 *
	 * @param  string $key The cache key name.
	 * @param  mixed  $val The value to cache.
	 * @return mixed  The value being cached.
	 */
	public static function cache_set( $key, $val ) {
		$duration = apply_filters( 'c2c_gcal_days_google_cache_duration', 2 * MINUTE_IN_SECONDS, $key, $val );
		set_transient( 'c2c_gcal_days_cache_' . sanitize_key( $key ), $val, $duration );
		return $val;
	}

	/**
	 * Delets a cached item with the given key.
	 *
	 * @param  string $key The cache key name.
	 */
	public static function cache_delete( $key ) {
		delete_transient( 'c2c_gcal_days_cache_' . sanitize_key( $key ) );
	}


	/****
	 * GOOGLE CALENDAR API
	 ****/


	/**
	 * Performs the actual Google Calendar API request.
	 *
	 * @since 1.0
	 *
	 * @param  string $action      The API action.
	 * @param  string $calendar_id Optional. The calendar ID. If blank, uses the default configured in settings.
	 * @param  array  $query_args  Optional. Query arguments to be appended to the request URL.
	 * @return object The body of the response.
	 */
	private static function api_request( $action, $calendar_id = '', $query_args = array() ) {
		$access_token = self::get_access_token();

		if ( 'me' == $calendar_id ) {
			$url = 'https://www.googleapis.com/calendar/v3/users/me';
		} else {
			if ( empty( $calendar_id ) ) {
				$calendar_id = c2c_GCalDaysSettings::get_options( 'default_calendar_id' );
			}

			if ( empty( $calendar_id ) || ! self::is_valid_calendar( $calendar_id ) ) {
				return '(blank or invalid calendar id)';//-1;
			}

			$url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode( $calendar_id );
		}

		$url .= '/' . urlencode( $action );

		if ( ! empty( $query_args ) ) {
			$url .= '?' . http_build_query( $query_args );
		}

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			)
		) );

		if ( self::is_api_error( $response ) ) {
			return self::get_api_error( $response );
		}

		$body = json_decode( $response['body'] );

		return $body;
	}

	/**
	 * Indicates if the provided calendar ID is associated with one of the
	 * user's calendars.
	 *
	 * @since 1.0
	 *
	 * @param  string $calendar_id The calendar ID.
	 * @return bool   Is the calendar ID valid?
	 */
	public static function is_valid_calendar( $calendar_id ) {
		if ( empty( $calendar_id ) ) {
			return false;
		}

		// TODO: Do this for real
		return true;
	}

	public static function is_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			return true;
		}
		$body = json_decode( $response['body'] );
		return property_exists( $body, 'error' );
	}

	public static function get_api_error( $response ) {
		$show_error = true;

		if ( ! self::is_api_error( $response ) ) {
			return;
		}

		if ( $show_error ) {
			if ( is_wp_error( $response ) ) {
				$msg = sprintf( 'ERROR: %s', $response->get_error_message() );
			} else {
				$body = json_decode( $response['body'] );
				$error = $body->error;
				if ( is_object( $error ) ) {
					$error = $error->message;
				}
				if ( 200 != $response['response']['code'] ) {
					$msg = sprintf( 'API ERROR: [%s] - %s (%s)', $response['response']['code'], $response['response']['message'], $error );
				} else {
					$msg = sprintf( 'API ERROR: %s', $error );
				}
			}
			return $msg;
		} else {
			return -1;
		}
	}

	/**
	 * Returns the list of calendars associated with the user.
	 *
	 * @since 1.0
	 *
	 * @return array Array of objects representing the user's calendars.
	 */
	public static function get_calendars() {
		if ( $val = self::cache_get( 'get_calendars' ) ) {
			return $val;
		}

		$body = self::api_request( 'calendarList', 'me' );

		if ( is_object( $body ) && property_exists( $body, 'items') ) {
			self::cache_set( 'get_calendars', $body->items );
			return $body->items;
		} else {
			return $body;
		}
	}

	/**
	 * Returns the number of days since the most recent past event matching the
	 * provided search criteria.
	 *
	 * @since 1.0
	 *
	 * @param  string $search The search phrase.
	 * @param  string $calendar_id Optional. The calendar ID. If blank, uses the default configured in settings.
	 * @return int    The number of days. -1 if no match was found or some other error occurred.
	 */
	public static function days_since( $search, $calendar_id = '' ) {
		$query_args = array(
			'q'            => $search,
			'maxResults'   => 25,
			'orderBy'      => 'startTime',
			'singleEvents' => 'true',
			'timeMax'      => date( DateTime::ATOM ),
		);

		$body = self::api_request( 'events', $calendar_id, $query_args );

		$cnt = 0;

		while ( is_object( $body ) && property_exists( $body, 'nextPageToken' ) ) {
			// TODO: Be more clever about finding most recent event amidst a significant number of events.
			// For now, bail if there are over 6 pages of results
			if ( $cnt++ > 6 ) {
				$body = -1;
				break;
			}
			$query_args['pageToken'] = $body->nextPageToken;
			$body = self::api_request( 'events', $calendar_id, $query_args );
		}

		if ( ! is_object( $body ) ) {
			return $body;
		}

		// Because of limitations of the Google Calendar API, events can't be requested in reverse chronological order.
		// Therefore, we basically have to request all matching events until we exhaust the results and use the latest.
		// The API doesn't allow offsets either. The only way to minimize the past results would be to define a start
		// date after which the search takes place. In the future this can get smarter by storing a valud to use for
		// 'timeMin' to reduce the number of paged results we need to obtain.
		$days = -1;
		if ( property_exists( $body, 'items' ) && ! empty( $body->items ) ) {
			$most_recent = array_pop( $body->items );
			$date = $most_recent->start->dateTime;
			$diff = time() - strtotime( $date );
			$days = floor( $diff / DAY_IN_SECONDS );
			// TODO: Cache here
		}
		return $days;
	}

	/**
	 * Returns the number of days until the closest upcoming event matching the
	 * provided search criteria.
	 *
	 * @since 1.0
	 *
	 * @param  string $search The search phrase.
	 * @param  string $calendar_id Optional. The calendar ID. If blank, uses the default configured in settings.
	 * @return int    The number of days. -1 if no match was found or some other error occurred.
	 */
	public static function days_until( $search, $calendar_id = '' ) {
		$query_args = array(
			'q'            => $search,
			'maxResults'   => 1,
			'orderBy'      => 'startTime',
			'singleEvents' => 'true',
			'timeMin'      => date( DateTime::ATOM ),
		);

		$body = self::api_request( 'events', $calendar_id, $query_args );

		if ( ! is_object( $body ) ) {
			return $body;
		}

		$days = -1;
		if ( property_exists( $body, 'items' ) && ! empty( $body->items ) ) {
			$most_recent = array_pop( $body->items );
			$date = $most_recent->start->dateTime;
			$diff = strtotime( $date ) - time();
			$days = floor( $diff / DAY_IN_SECONDS );
			// TODO: Cache here
		}
		return $days;
	}

}

endif;

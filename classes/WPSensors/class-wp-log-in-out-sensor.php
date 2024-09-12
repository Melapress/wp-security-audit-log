<?php
/**
 * Sensor: Login/Logout sensor.
 *
 * Login/Logout sensor class file.
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WP_Log_In_Out_Sensor' ) ) {
	/**
	 * Login/Logout sensor.
	 *
	 * 1000 User logged in
	 * 1001 User logged out
	 * 1002 Login failed
	 * 1003 Login failed / non existing user
	 * 1004 Login blocked
	 * 4003 User has changed his or her password
	 *
	 * @package    wsal
	 * @subpackage sensors
	 */
	class WP_Log_In_Out_Sensor {

		/**
		 * Transient name.
		 * WordPress will prefix the name with "_transient_" or "_transient_timeout_" in the options table.
		 */
		const TRANSIENT_FAILEDLOGINS              = 'wsal-failedlogins-known';
		const TRANSIENT_FAILEDLOGINS_LAST         = 'wsal-failedlogins-last';
		const TRANSIENT_FAILEDLOGINS_NO_INCREMENT = 'wsal-failedlogins-no-increment';
		const TRANSIENT_FAILEDLOGINS_UNKNOWN      = 'wsal-failedlogins-unknown';

		/**
		 * Current user object
		 *
		 * @var \WP_User
		 *
		 * @since 4.5.0
		 */
		private static $current_user = null;

		/**
		 * Is that a lgin sensor or not?
		 * Sensors doesn't need to have this property, except where they explicitly not to set that value.
		 *
		 * @var boolean
		 *
		 * @since 4.5.0
		 */
		private static $login_sensor = true;

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			\add_action( 'set_auth_cookie', array( __CLASS__, 'event_login' ), 10, 6 );
			\add_action( 'wp_logout', array( __CLASS__, 'event_logout' ), 5 );
			\add_action( 'password_reset', array( __CLASS__, 'event_password_reset' ), 10, 2 );
			\add_action( 'wp_login_failed', array( __CLASS__, 'event_login_failure' ) );
			\add_action( 'clear_auth_cookie', array( __CLASS__, 'get_current_user' ), 10 );
			\add_action( 'lostpassword_post', array( __CLASS__, 'event_user_requested_pw_reset' ), 10, 2 );

			if ( WP_Helper::is_plugin_active( 'user-switching/user-switching.php' ) ) {
				\add_action( 'switch_to_user', array( __CLASS__, 'user_switched_event' ), 10, 2 );
			}
		}

		/**
		 * Is that a front end sensor? The sensors doesn't need to have that method implemented, except if they want to specifically set that value.
		 *
		 * @return boolean
		 *
		 * @since 4.5.0
		 */
		public static function is_login_sensor() {
			return self::$login_sensor;
		}

		/**
		 * That needs to be registered as a frontend sensor, when the admin sets the plugin to monitor the login from 3rd parties.
		 *
		 * @return boolean
		 *
		 * @since 4.5.1
		 */
		public static function is_frontend_sensor(): bool {
			$frontend_events = Settings_Helper::get_frontend_events();
			$should_load     = ! empty( $frontend_events['register'] ) || ! empty( $frontend_events['login'] );

			if ( $should_load ) {
				return true;
			}

			return false;
		}

		/**
		 * Sets current user.
		 *
		 * @since 4.5.0
		 */
		public static function get_current_user() {
			self::$current_user = User_Helper::get_current_user();
		}

		/**
		 * Event Login.
		 *
		 * @param string $auth_cookie Authentication cookie value.
		 * @param int    $expire      The time the login grace period expires as a UNIX timestamp.
		 *                            Default is 12 hours past the cookie's expiration time.
		 * @param int    $expiration  The time when the authentication cookie expires as a UNIX timestamp.
		 *                            Default is 14 days from now.
		 * @param int    $user_id     User ID.
		 * @param string $scheme      Authentication scheme. Values include 'auth' or 'secure_auth'.
		 * @param string $token       User's session token to use for this cookie.
		 *
		 * @since 4.5.0
		 */
		public static function event_login( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
			// Get global POST array.
			$post_array = filter_input_array( INPUT_POST );

			/**
			 * Check for Ultimate Member plugin.
			 *
			 * @since 3.1.6
			 */
			if ( isset( $post_array['_um_account'] ) && isset( $post_array['_um_account_tab'] ) && 'password' === $post_array['_um_account_tab'] ) {
				/**
				 * If the data is coming from UM plugin account change
				 * password page, check for change in password.
				 *
				 * 1. Check previous password.
				 * 2. Check new password.
				 * 3. Check confirm new password.
				 * 4. If current & new password don't match.
				 * 5. And new & confirm password are same then log change password alert.
				 */
				if ( isset( $post_array['current_user_password'] ) // Previous password.
				&& isset( $post_array['user_password'] ) // New password.
				&& isset( $post_array['confirm_user_password'] ) // Confirm new password.
				&& $post_array['current_user_password'] !== $post_array['user_password'] // If current & new password don't match.
				&& $post_array['user_password'] === $post_array['confirm_user_password'] ) { // And new & confirm password are same then.
					// Get user.
					$user = get_user_by( 'id', $user_id );

					// Log user changed password alert.
					if ( ! empty( $user ) ) {
						$user_roles = User_Helper::get_user_roles( $user );

						Alert_Manager::trigger_event(
							4003,
							array(
								'Username'         => $user->user_login,
								'CurrentUserID'    => $user->ID,
								'CurrentUserRoles' => $user_roles,
							),
							true
						);
					}
				}
				return; // Return.
			}

			$user = get_user_by( 'id', $user_id );
			// bail early if we did not get a user object.
			if ( ! is_a( $user, '\WP_User' ) ) {
				return;
			}
			$user_login = $user->data->user_login;
			$user_roles = User_Helper::get_user_roles( $user );

			$alert_data = array(
				'Username'         => $user_login,
				'CurrentUserID'    => $user_id,
				'CurrentUserRoles' => $user_roles,
			);
			if ( class_exists( '\WSAL\Helpers\User_Sessions_Helper' ) ) {
				$alert_data['SessionID'] = \WSAL\Helpers\User_Sessions_Helper::hash_token( $token );
			}

			Alert_Manager::trigger_event_if(
				1000,
				$alert_data,
				/**
				* Don't fire if the user is changing their password via admin profile page.
				*
				* @return bool
				*/
				function () {
					return ! ( Alert_Manager::will_or_has_triggered( 4003 ) || Alert_Manager::has_triggered( 1000 ) || Alert_Manager::will_or_has_triggered( 1005 ) );
				}
			);
		}

		/**
		 * Event Logout.
		 *
		 * @since 4.5.0
		 */
		public static function event_logout() {
			if ( self::$current_user->ID ) {
				// get the list of excluded users.
				$excluded_users    = Settings_Helper::get_excluded_monitoring_users();
				$excluded_user_ids = array();
				// convert excluded usernames into IDs.
				if ( ! empty( $excluded_users ) && is_array( $excluded_users ) ) {
					foreach ( $excluded_users as $excluded_user ) {
						$user                = get_user_by( 'login', $excluded_user );
						$excluded_user_ids[] = $user->ID;
					}
				}
				// bail early if this user is in the excluded ids list.
				if ( in_array( self::$current_user->ID, $excluded_user_ids, true ) ) {
					return;
				}

				Alert_Manager::trigger_event(
					1001,
					array(
						'CurrentUserID'    => self::$current_user->ID,
						'CurrentUserRoles' => User_Helper::get_user_roles( self::$current_user ),
					),
					true
				);
			}
		}

		/**
		 * Login failure limit count.
		 *
		 * @return int
		 *
		 * @since 4.5.0
		 */
		// protected static function get_login_failure_log_limit() {
		// return Settings_Helper::get_option_value( 'log-failed-login-limit', 10 );
		// }

		/**
		 * Non-existing Login failure limit count.
		 *
		 * @return int
		 *
		 * @since 4.5.0
		 */
		protected static function get_visitor_login_failure_log_limit() {
			return intval( Settings_Helper::get_option_value( 'log-visitor-failed-login-limit', 10 ) );
		}

		/**
		 * Expiration of the transient saved in the WP database.
		 *
		 * @return integer Time until expiration in seconds from now
		 *
		 * @since 4.5.0
		 */
		protected static function get_login_failure_expiration() {
			return 12 * 60 * 60;
		}

		/**
		 * Check failure limit.
		 *
		 * @param string  $ip      - IP address.
		 * @param integer $site_id - Blog ID.
		 * @param WP_User $user    - User object.
		 *
		 * @return boolean - Passed limit true|false.
		 *
		 * @since 4.5.0
		 */
		// protected static function is_past_login_failure_limit( $ip, $site_id, $user ) {
		// if ( $user ) {
		// if ( -1 === (int) self::get_login_failure_log_limit() ) {
		// return false;
		// } else {
		// $data_known = WP_Helper::get_transient( self::TRANSIENT_FAILEDLOGINS );
		// return ( false !== $data_known ) && isset( $data_known[ $site_id . ':' . $user->ID . ':' . $ip ] ) && ( $data_known[ $site_id . ':' . $user->ID . ':' . $ip ] >= self::get_login_failure_log_limit() );
		// }
		// } elseif ( -1 === (int) self::get_visitor_login_failure_log_limit() ) {
		// return false;
		// } else {
		// $data_unknown = WP_Helper::get_transient( self::TRANSIENT_FAILEDLOGINS_UNKNOWN );
		// return ( false !== $data_unknown ) && isset( $data_unknown[ $site_id . ':' . $ip ] ) && ( $data_unknown[ $site_id . ':' . $ip ] >= self::get_visitor_login_failure_log_limit() );
		// }
		// }

		/**
		 * Increment failure limit.
		 *
		 * @param string  $ip - IP address.
		 * @param integer $site_id - Blog ID.
		 * @param WP_User $user - User object.
		 *
		 * @since 4.5.0
		 */
		protected static function increment_login_failure( $ip, $site_id, $user ) {
			if ( $user ) {
				$data_known = WP_Helper::get_transient( self::TRANSIENT_FAILEDLOGINS );
				$last_inc   = WP_Helper::get_transient( self::TRANSIENT_FAILEDLOGINS_LAST );

				// Check if this has already been logged and counted.
				if ( $last_inc && $last_inc === $user->ID ) {
					WP_Helper::set_transient( self::TRANSIENT_FAILEDLOGINS_NO_INCREMENT, $user->ID, 10 );
					return;
				}
				if ( ! $data_known ) {
					$data_known = array();
				}
				if ( ! isset( $data_known[ $site_id . ':' . $user->ID . ':' . $ip ] ) ) {
					$data_known[ $site_id . ':' . $user->ID . ':' . $ip ] = 1;
				}
				++$data_known[ $site_id . ':' . $user->ID . ':' . $ip ];
				WP_Helper::set_transient( self::TRANSIENT_FAILEDLOGINS, $data_known, self::get_login_failure_expiration() );
				WP_Helper::set_transient( self::TRANSIENT_FAILEDLOGINS_LAST, $user->ID, 10 );
			} else {
				$data_unknown = WP_Helper::get_transient( self::TRANSIENT_FAILEDLOGINS_UNKNOWN );
				if ( ! $data_unknown ) {
					$data_unknown = array();
				}
				if ( ! isset( $data_unknown[ $site_id . ':' . $ip ] ) ) {
					$data_unknown[ $site_id . ':' . $ip ] = 1;
				}
				++$data_unknown[ $site_id . ':' . $ip ];
				WP_Helper::set_transient( self::TRANSIENT_FAILEDLOGINS_UNKNOWN, $data_unknown, self::get_login_failure_expiration() );
			}
		}

		/**
		 * Event Login failure.
		 *
		 * @param string $username Username.
		 *
		 * @since 4.5.0
		 */
		public static function event_login_failure( $username ) {
			// list($y, $m, $d) = explode( '-', gmdate( 'Y-m-d' ) );

			// $m = (int) $m;
			// $y = (int) $y;
			// $d = (int) $d;

			$ip = Settings_Helper::get_main_client_ip();

			// Filter $_POST global array for security.
			$post_array = filter_input_array( INPUT_POST );

			$username       = isset( $post_array['log'] ) ? $post_array['log'] : $username;
			$username       = \sanitize_user( $username );
			$new_alert_code = 1003;
			$user           = \get_user_by( 'login', $username );
			// If we still don't have the user, lets look for them using there email address.
			if ( empty( $user ) ) {
				$user = \get_user_by( 'email', $username );
			}

			$site_id = ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );
			if ( $user ) {
				$new_alert_code = 1002;
				$user_roles     = User_Helper::get_user_roles( $user );
			}

			// Check if the alert is disabled from the "Enable/Disable Alerts" section.
			if ( ! Alert_Manager::is_enabled( $new_alert_code ) ) {
				return;
			}

			// if ( self::is_past_login_failure_limit( $ip, $site_id, $user ) ) {
			// return;
			// }

			if ( 1002 === $new_alert_code ) {
				if ( ! Alert_Manager::check_enable_user_roles( $username, $user_roles ) ) {
					return;
				}

				if ( Alert_Manager::will_or_has_triggered( 1004 ) ) {
					// Skip if 1004 (session block) is already in place.
					return;
				}

				/** Check known users */
				/*
				$occ = Occurrences_Entity::build_multi_query(
					'   WHERE client_ip = %s '
					. ' AND username = %s '
					. ' AND alert_id = %d '
					. ' AND site_id = %d '
					. ' AND ( created_on BETWEEN %d AND %d );',
					array(
						$ip,
						$username,
						1002,
						$site_id,
						mktime( 0, 0, 0, $m, $d, $y ),
						mktime( 0, 0, 0, $m, $d + 1, $y ) - 1,
					)
				);
				$occ = count( $occ ) ? $occ[0] : null;

				if ( ! empty( $occ ) ) {
					// Update existing record exists user.
					self::increment_login_failure( $ip, $site_id, $user );

					$no_increment            = WP_Helper::get_transient( self::TRANSIENT_FAILEDLOGINS_NO_INCREMENT );
					$attempts                = Occurrences_Entity::get_meta_value( $occ, 'Attempts', 0 );
					$new                     = ( $no_increment && $no_increment === $user->ID ) ? $attempts : $attempts + 1;
					$login_failure_log_limit = self::get_login_failure_log_limit();
					if ( - 1 !== $login_failure_log_limit && $new > $login_failure_log_limit ) {
						$new = $login_failure_log_limit . '+';
					}

					Metadata_Entity::update_by_name_and_occurrence_id( 'Attempts', $new, $occ['id'] );

					unset( $occ['created_on'] );
					Occurrences_Entity::save( $occ );
				} else { */
				{
					// Create a new record exists user.
					Alert_Manager::trigger_event_if(
						$new_alert_code,
						array(
							'Attempts'         => 1,
							'Username'         => $username,
							'CurrentUserID'    => $user->ID,
							'LogFileText'      => '',
							'CurrentUserRoles' => $user_roles,
						),
						/**
						* Skip if 1004 (session block) is already in place.
						*
						* @return bool
						*/
						function () {
							return ! ( Alert_Manager::will_or_has_triggered( 1004 ) || Alert_Manager::has_triggered( 1002 ) );
						}
					);
				}
			} else {
				/*
				$occ_unknown = Occurrences_Entity::build_multi_query(
					' WHERE client_ip = %s '
					. ' AND alert_id = %d '
					. ' AND site_id = %d '
					. ' AND ( created_on BETWEEN %d AND %d );',
					array(
						$ip,
						1003,
						$site_id,
						mktime( 0, 0, 0, $m, $d, $y ),
						mktime( 0, 0, 0, $m, $d + 1, $y ) - 1,
					)
				);

				$occ_unknown = count( $occ_unknown ) ? $occ_unknown[0] : null;
				if ( ! empty( $occ_unknown ) ) {
					// Update existing record not exists user.
					self::increment_login_failure( $ip, $site_id, false );

					// Increase the number of attempts.
					$new = Occurrences_Entity::get_meta_value( $occ_unknown, 'Attempts', 0 ) + 1;

					// If login attempts pass allowed number of attempts then stop increasing the attempts.
					$failure_limit = self::get_visitor_login_failure_log_limit();
					if ( -1 !== $failure_limit && $new > $failure_limit ) {
						$new = $failure_limit . '+';
					}

					// Update the number of login attempts.
					Metadata_Entity::update_by_name_and_occurrence_id( 'Attempts', $new, $occ_unknown['id'] );

					// Get users from alert.
					$users = \maybe_unserialize( Metadata_Entity::load_by_name_and_occurrence_id( 'Users', $occ_unknown['id'] )['value'] );

					// Update it if username is not already present in the array.
					if ( ! empty( $users ) && is_array( $users ) && ! in_array( $username, $users, true ) ) {
						$users[] = $username;
						Metadata_Entity::update_by_name_and_occurrence_id( 'Users', $users, $occ_unknown['id'] );
					} else {
						// In this case the value doesn't exist so set the value to array.
						$users = array( $username );
					}

					unset( $occ_unknown['created_on'] );
					Occurrences_Entity::save( $occ_unknown );
				} else {
					*/
				{
					// Make an array of usernames.
					// $users = array( $username );

					// Log an alert for a login attempt with unknown username.
					Alert_Manager::trigger_event(
						$new_alert_code,
						array(
							'Attempts'    => 1,
							'Users'       => $username,
							'LogFileText' => '',
							'ClientIP'    => $ip,
						)
					);
				}
			}
		}

		/**
		 * Event changed password.
		 *
		 * @param WP_User $user - User object.
		 * @param string  $new_pass - New Password.
		 *
		 * @since 4.5.0
		 */
		public static function event_password_reset( $user, $new_pass ) {
			if ( ! empty( $user ) ) {
				$user_roles = User_Helper::get_user_roles( $user );

				Alert_Manager::trigger_event(
					4003,
					array(
						'Username'         => $user->user_login,
						'CurrentUserID'    => $user->ID,
						'CurrentUserRoles' => $user_roles,
					),
					true
				);
			}
		}

		/**
		 * User Switched.
		 *
		 * Current user switched to another user event.
		 *
		 * @param int $new_user_id - New user id.
		 * @param int $old_user_id - Old user id.
		 *
		 * @since 4.5.0
		 */
		public static function user_switched_event( $new_user_id, $old_user_id ) {
			$target_user       = get_user_by( 'ID', $new_user_id );
			$target_user_roles = User_Helper::get_user_roles( $target_user );
			$target_user_roles = implode( ', ', $target_user_roles );
			$old_user          = get_user_by( 'ID', $old_user_id );
			$old_user_roles    = User_Helper::get_user_roles( $old_user );

			Alert_Manager::trigger_event(
				1008,
				array(
					'TargetUserName'   => $target_user->user_login,
					'TargetUserRole'   => $target_user_roles,
					'Username'         => $old_user->user_login,
					'CurrentUserID'    => $old_user->ID,
					'CurrentUserRoles' => $old_user_roles,
				)
			);
		}

		/**
		 * User has requested a password reset.
		 *
		 * @param object $errors Current WP_errors object.
		 * @param object $user   User making the request.
		 *
		 * @since 4.5.0
		 */
		public static function event_user_requested_pw_reset( $errors, $user = null ) {

			// If we don't have the user, do nothing.
			if ( is_null( $user ) || ! isset( $user->roles ) ) {
				return;
			}

			$user_roles = User_Helper::get_user_roles( $user );

			Alert_Manager::trigger_event(
				1010,
				array(
					'Username'         => $user->user_login,
					'CurrentUserRoles' => $user_roles,
					// Current user ID must be set explicitly as the user is not logged in when this happens.
					'CurrentUserID'    => $user->ID,
				),
				true
			);
		}
	}
}

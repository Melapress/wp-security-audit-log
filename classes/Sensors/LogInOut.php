<?php
/**
 * Sensor: Log In & Log Out
 *
 * Log In & Out sensor class file.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage sensors
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
class WSAL_Sensors_LogInOut extends WSAL_AbstractSensor {

	/**
	 * Transient name.
	 * WordPress will prefix the name with "_transient_" or "_transient_timeout_" in the options table.
	 */
	const TRANSIENT_FAILEDLOGINS         = 'wsal-failedlogins-known';
	const TRANSIENT_FAILEDLOGINS_UNKNOWN = 'wsal-failedlogins-unknown';

	/**
	 * Current user object
	 *
	 * @var WP_User
	 */
	protected $current_user = null;

	/**
	 * {@inheritDoc}
	 */
	public function hook_events() {
		add_action( 'set_auth_cookie', array( $this, 'event_login' ), 10, 6 );
		add_action( 'wp_logout', array( $this, 'event_logout' ), 5 );
		add_action( 'password_reset', array( $this, 'event_password_reset' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'event_login_failure' ) );
		add_action( 'clear_auth_cookie', array( $this, 'get_current_user' ), 10 );
		add_action( 'lostpassword_post', array( $this, 'event_user_requested_pw_reset' ), 10, 2 );

		if ( WpSecurityAuditLog::is_twofactor_active() ) {
			add_action( 'login_redirect', array( $this, 'event_2fa_login' ), 10, 1 );
		}

		if ( WpSecurityAuditLog::is_plugin_active( 'user-switching/user-switching.php' ) ) {
			add_action( 'switch_to_user', array( $this, 'user_switched_event' ), 10, 2 );
		}
	}

	/**
	 * Sets current user.
	 */
	public function get_current_user() {
		$this->current_user = wp_get_current_user();
	}

	/**
	 * Login Event for Two-Factor plugin.
	 *
	 * @since 3.3
	 *
	 * @param string $redirect_url – Redirect URL.
	 * @return string
	 */
	public function event_2fa_login( $redirect_url ) {
		// @codingStandardsIgnoreStart
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$user_id  = isset( $_POST['wp-auth-id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['wp-auth-id'] ) ) : '';
		// @codingStandardsIgnoreEnd

		// Default Two-Factor options.
		$providers_2fa = array( 'Two_Factor_Email', 'Two_Factor_Totp', 'Two_Factor_FIDO_U2F', 'Two_Factor_Backup_Codes', 'Two_Factor_Dummy' );

		// Get users.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $redirect_url;
		}

		// If provider and user are set and provider is known then log the event.
		if ( $provider && $user && in_array( $provider, $providers_2fa, true ) ) {
			// Get user roles.
			$user_roles = $this->plugin->settings()->get_current_user_roles( $user->roles );

			if ( $this->plugin->settings()->is_login_super_admin( $user->user_login ) ) {
				$user_roles[] = 'superadmin';
			}

			$this->plugin->alerts->trigger_event(
				1000,
				array(
					'Username'         => $user->user_login,
					'CurrentUserRoles' => $user_roles,
				),
				true
			);
		}

		return $redirect_url;
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
	 */
	public function event_login( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
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
					$user_roles = $this->plugin->settings()->get_current_user_roles( $user->roles );
					$this->plugin->alerts->trigger_event(
						4003,
						array(
							'Username'         => $user->user_login,
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
		$user_roles = $this->plugin->settings()->get_current_user_roles( $user->roles );

		if ( $this->plugin->settings()->is_login_super_admin( $user_login ) ) {
			$user_roles[] = 'superadmin';
		}

		$alert_data = array(
			'Username'         => $user_login,
			'CurrentUserRoles' => $user_roles,
		);
		if ( class_exists( 'WSAL_UserSessions_Helpers' ) ) {
			$alert_data['SessionID'] = WSAL_UserSessions_Helpers::hash_token( $token );
		}

		$this->plugin->alerts->trigger_event_if(
			1000,
			$alert_data,
			/**
			 * Don't fire if the user is changing their password via admin profile page.
			 *
			 * @param WSAL_AlertManager $manager
			 *
			 * @return bool
			 */
			function ( $manager ) {
				return ! $manager->will_or_has_triggered( 4003 );
			}
		);

	}

	/**
	 * Event Logout.
	 */
	public function event_logout() {
		if ( $this->current_user->ID ) {
			// get the list of excluded users.
			$excluded_users    = $this->plugin->settings()->get_excluded_monitoring_users();
			$excluded_user_ids = array();
			// convert excluded usernames into IDs.
			if ( ! empty( $excluded_users ) && is_array( $excluded_users ) ) {
				foreach ( $excluded_users as $excluded_user ) {
					$user                = get_user_by( 'login', $excluded_user );
					$excluded_user_ids[] = $user->ID;
				}
			}
			// bail early if this user is in the excluded ids list.
			if ( in_array( $this->current_user->ID, $excluded_user_ids, true ) ) {
				return;
			}
			$this->plugin->alerts->trigger_event(
				1001,
				array(
					'CurrentUserID'    => $this->current_user->ID,
					'CurrentUserRoles' => $this->plugin->settings()->get_current_user_roles( $this->current_user->roles ),
				),
				true
			);
		}
	}

	/**
	 * Login failure limit count.
	 *
	 * @return int
	 */
	protected function get_login_failure_log_limit() {
		return $this->plugin->settings()->get_failed_login_limit();
	}

	/**
	 * Non-existing Login failure limit count.
	 *
	 * @return int
	 */
	protected function get_visitor_login_failure_log_limit() {
		return $this->plugin->settings()->get_visitor_failed_login_limit();
	}

	/**
	 * Expiration of the transient saved in the WP database.
	 *
	 * @return integer Time until expiration in seconds from now
	 */
	protected function get_login_failure_expiration() {
		return 12 * 60 * 60;
	}

	/**
	 * Check failure limit.
	 *
	 * @param string  $ip - IP address.
	 * @param integer $site_id - Blog ID.
	 * @param WP_User $user - User object.
	 * @return boolean - Passed limit true|false.
	 */
	protected function is_past_login_failure_limit( $ip, $site_id, $user ) {
		$get_fn = $this->is_multisite() ? 'get_site_transient' : 'get_transient';
		if ( $user ) {
			if ( -1 === (int) $this->get_login_failure_log_limit() ) {
				return false;
			} else {
				$data_known = $get_fn( self::TRANSIENT_FAILEDLOGINS );
				return ( false !== $data_known ) && isset( $data_known[ $site_id . ':' . $user->ID . ':' . $ip ] ) && ( $data_known[ $site_id . ':' . $user->ID . ':' . $ip ] >= $this->get_login_failure_log_limit() );
			}
		} else {
			if ( -1 === (int) $this->get_visitor_login_failure_log_limit() ) {
				return false;
			} else {
				$data_unknown = $get_fn( self::TRANSIENT_FAILEDLOGINS_UNKNOWN );
				return ( false !== $data_unknown ) && isset( $data_unknown[ $site_id . ':' . $ip ] ) && ( $data_unknown[ $site_id . ':' . $ip ] >= $this->get_visitor_login_failure_log_limit() );
			}
		}
	}

	/**
	 * Increment failure limit.
	 *
	 * @param string  $ip - IP address.
	 * @param integer $site_id - Blog ID.
	 * @param WP_User $user - User object.
	 */
	protected function increment_login_failure( $ip, $site_id, $user ) {
		$get_fn = $this->is_multisite() ? 'get_site_transient' : 'get_transient';
		$set_fn = $this->is_multisite() ? 'set_site_transient' : 'set_transient';
		if ( $user ) {
			$data_known = $get_fn( self::TRANSIENT_FAILEDLOGINS );
			if ( ! $data_known ) {
				$data_known = array();
			}
			if ( ! isset( $data_known[ $site_id . ':' . $user->ID . ':' . $ip ] ) ) {
				$data_known[ $site_id . ':' . $user->ID . ':' . $ip ] = 1;
			}
			$data_known[ $site_id . ':' . $user->ID . ':' . $ip ]++;
			$set_fn( self::TRANSIENT_FAILEDLOGINS, $data_known, $this->get_login_failure_expiration() );
		} else {
			$data_unknown = $get_fn( self::TRANSIENT_FAILEDLOGINS_UNKNOWN );
			if ( ! $data_unknown ) {
				$data_unknown = array();
			}
			if ( ! isset( $data_unknown[ $site_id . ':' . $ip ] ) ) {
				$data_unknown[ $site_id . ':' . $ip ] = 1;
			}
			$data_unknown[ $site_id . ':' . $ip ]++;
			$set_fn( self::TRANSIENT_FAILEDLOGINS_UNKNOWN, $data_unknown, $this->get_login_failure_expiration() );
		}
	}

	/**
	 * Event Login failure.
	 *
	 * @param string $username Username.
	 */
	public function event_login_failure( $username ) {
		list($y, $m, $d) = explode( '-', date( 'Y-m-d' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		$ip = $this->plugin->settings()->get_main_client_ip();

		// Filter $_POST global array for security.
		$post_array = filter_input_array( INPUT_POST );

		$username       = isset( $post_array['log'] ) ? $post_array['log'] : $username;
		$username       = sanitize_user( $username );
		$new_alert_code = 1003;
		$user           = get_user_by( 'login', $username );
		// If we still dont have the user, lets look for them using there email address.
		if ( empty( $user ) ) {
			$user = get_user_by( 'email', $username );
		}

		$site_id = ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );
		if ( $user ) {
			$new_alert_code = 1002;
			$user_roles     = $this->plugin->settings()->get_current_user_roles( $user->roles );
			if ( $this->plugin->settings()->is_login_super_admin( $username ) ) {
				$user_roles[] = 'superadmin';
			}
		}

		// Check if the alert is disabled from the "Enable/Disable Alerts" section.
		if ( ! $this->plugin->alerts->is_enabled( $new_alert_code ) ) {
			return;
		}

		if ( $this->is_past_login_failure_limit( $ip, $site_id, $user ) ) {
			return;
		}

		$obj_occurrence = new WSAL_Models_Occurrence();

		if ( 1002 === $new_alert_code ) {
			if ( ! $this->plugin->alerts->check_enable_user_roles( $username, $user_roles ) ) {
				return;
			}
			$occ = $obj_occurrence->check_known_users(
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

			if ( $this->plugin->alerts->will_or_has_triggered( 1004 ) ) {
				// Skip if 1004 (session block) is already in place.
				return;
			}

			if ( ! empty( $occ ) ) {
				// Update existing record exists user.
				$this->increment_login_failure( $ip, $site_id, $user );
				$new = $occ->get_meta_value( 'Attempts', 0 ) + 1;

				$login_failure_log_limit = $this->get_login_failure_log_limit();
				if ( - 1 !== $login_failure_log_limit && $new > $login_failure_log_limit ) {
					$new = $login_failure_log_limit . '+';
				}

				$occ->update_meta_value( 'Attempts', $new );
				$occ->username   = $username;
				$occ->created_on = null;
				$occ->save();
			} else {
				// Create a new record exists user.
				$this->plugin->alerts->trigger_event(
					$new_alert_code,
					array(
						'Attempts'         => 1,
						'Username'         => $username,
						'LogFileText'      => '',
						'CurrentUserRoles' => $user_roles,
					),
					/**
					 * Skip if 1004 (session block) is already in place.
					 *
					 * @param WSAL_AlertManager $manager
					 *
					 * @return bool
					 */
					function ( $manager ) {
						return ! $manager->will_or_has_triggered( 1004 );
					}
				);
			}
		} else {
			$occ_unknown = $obj_occurrence->check_unknown_users(
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
				$this->increment_login_failure( $ip, $site_id, false );

				// Increase the number of attempts.
				$new = $occ_unknown->get_meta_value( 'Attempts', 0 ) + 1;

				// If login attempts pass allowed number of attempts then stop increasing the attempts.
				$failure_limit = $this->get_visitor_login_failure_log_limit();
				if ( -1 !== $failure_limit && $new > $failure_limit ) {
					$new = $failure_limit . '+';
				}

				// Update the number of login attempts.
				$occ_unknown->update_meta_value( 'Attempts', $new );

				// Get users from alert.
				$users = $occ_unknown->get_meta_value( 'Users' );

				// Update it if username is not already present in the array.
				if ( ! empty( $users ) && is_array( $users ) && ! in_array( $username, $users, true ) ) {
					$users[] = $username;
					$occ_unknown->update_meta_value( 'Users', $users );
				} else {
					// In this case the value doesn't exist so set the value to array.
					$users = array( $username );
				}

				$occ_unknown->created_on = null;
				$occ_unknown->save();
			} else {
				// Make an array of usernames.
				$users = array( $username );

				// Log an alert for a login attempt with unknown username.
				$this->plugin->alerts->trigger_event(
					$new_alert_code,
					array(
						'Attempts'    => 1,
						'Users'       => $users,
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
	 */
	public function event_password_reset( $user, $new_pass ) {
		if ( ! empty( $user ) ) {
			$user_roles = $this->plugin->settings()->get_current_user_roles( $user->roles );
			$this->plugin->alerts->trigger_event(
				4003,
				array(
					'Username'         => $user->user_login,
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
	 * @since 3.4
	 *
	 * @param int $new_user_id - New user id.
	 * @param int $old_user_id - Old user id.
	 */
	public function user_switched_event( $new_user_id, $old_user_id ) {
		$target_user       = get_user_by( 'ID', $new_user_id );
		$target_user_roles = $this->plugin->settings()->get_current_user_roles( $target_user->roles );
		$target_user_roles = implode( ', ', $target_user_roles );
		$old_user          = get_user_by( 'ID', $old_user_id );
		$old_user_roles    = $this->plugin->settings()->get_current_user_roles( $old_user->roles );

		$this->plugin->alerts->trigger_event(
			1008,
			array(
				'TargetUserName'   => $target_user->user_login,
				'TargetUserRole'   => $target_user_roles,
				'Username'         => $old_user->user_login,
				'CurrentUserRoles' => $old_user_roles,
			)
		);
	}

	/**
	 * User has requested a password reset.
	 *
	 * @param object $errors Current WP_errors object.
	 * @param object $user   User making the request.
	 */
	public function event_user_requested_pw_reset( $errors, $user = null ) {

		// If we don't have the user, do nothing.
		if ( is_null( $user ) || ! isset( $user->roles ) ) {
			return;
		}

		$user_roles = $this->plugin->settings()->get_current_user_roles( $user->roles );
		$this->plugin->alerts->trigger_event(
			1010,
			array(
				'Username'         => $user->user_login,
				'CurrentUserRoles' => $user_roles,
			),
			true
		);
	}
}

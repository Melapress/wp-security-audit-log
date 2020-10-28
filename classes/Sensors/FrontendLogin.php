<?php
/**
 * Frontend user login sensor.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend user login sensor.
 */
class WSAL_Sensors_FrontendLogin extends WSAL_AbstractSensor {

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'set_auth_cookie', array( $this, 'event_login' ), 10, 6 );
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

		$user = get_user_by( 'id', $user_id );
		if ( ! is_a( $user, '\WP_User' ) ) {
			// not a user logging in, return early.
			return;
		}

		$user_login = $user->data->user_login;
		$user_roles = $this->plugin->settings()->GetCurrentUserRoles( $user->roles );

		if ( $this->plugin->settings()->IsLoginSuperAdmin( $user_login ) ) {
			$user_roles[] = 'superadmin';
		}

		$alert_data = array(
			'Username'         => $user_login,
			'CurrentUserRoles' => $user_roles,
		);

		//  @todo attach this using a filter, it should also remove the need for duplicated function hash_token below
		if ( class_exists( 'WSAL_UserSessions_Helpers' ) ) {
			$alert_data['SessionID'] = $this->hash_token( $token );
		}

		$this->plugin->alerts->Trigger(
			1000,
			$alert_data,
			true
		);
	}

	/**
	 * Hashes the given session token.
	 *
	 * NOTE: This is how core session manager does it.
	 *
	 * @param string $token Session token to hash.
	 * @return string A hash of the session token (a verifier).
	 */
	public static function hash_token( $token ) {
		// If ext/hash is not present, use sha1() instead.
		if ( function_exists( 'hash' ) ) {
			return hash( 'sha256', $token );
		} else {
			return sha1( $token );
		}
	}
}

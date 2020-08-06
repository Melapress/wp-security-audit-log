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
	 * TODO: update params doc block to match the new hook it's attached to.
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

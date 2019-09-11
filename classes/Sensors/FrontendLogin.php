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
		add_action( 'wp_login', array( $this, 'event_login' ), 10, 2 );
	}

	/**
	 * Event Login.
	 *
	 * @param string $user_login - Username.
	 * @param object $user - WP_User object.
	 */
	public function event_login( $user_login, $user ) {
		if ( empty( $user ) ) {
			$user = get_user_by( 'login', $user_login );
		}

		$user_roles = $this->plugin->settings->GetCurrentUserRoles( $user->roles );

		if ( $this->plugin->settings->IsLoginSuperAdmin( $user_login ) ) {
			$user_roles[] = 'superadmin';
		}

		$this->plugin->alerts->Trigger(
			1000,
			array(
				'Username'         => $user_login,
				'CurrentUserRoles' => $user_roles,
			),
			true
		);
	}
}

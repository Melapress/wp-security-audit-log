<?php
/**
 * Frontend user registeration sensor.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend user registeration sensor.
 */
class WSAL_Sensors_FrontendRegister extends WSAL_AbstractSensor {

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'user_register', array( $this, 'event_user_register' ) );
	}

	/**
	 * Triggered when a user is registered.
	 *
	 * @param int $user_id - User ID of the registered user.
	 */
	public function event_user_register( $user_id ) {
		$user         = get_userdata( $user_id );
		$ismu         = function_exists( 'is_multisite' ) && is_multisite();
		$event        = $ismu ? 4012 : ( is_user_logged_in() ? 4001 : 4000 );
		$current_user = wp_get_current_user();

		$this->plugin->alerts->Trigger(
			$event,
			array(
				'NewUserID'   => $user_id,
				'UserChanger' => ! empty( $current_user ) ? $current_user->user_login : '',
				'NewUserData' => (object) array(
					'Username'  => $user->user_login,
					'FirstName' => $user->user_firstname,
					'LastName'  => $user->user_lastname,
					'Email'     => $user->user_email,
					'Roles'     => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
				),
			),
			true
		);
	}
}

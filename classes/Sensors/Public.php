<?php
/**
 * Sensor: Public Activity
 *
 * Public/Visitor activity sensor class file.
 *
 * @since 3.3
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System Activity sensor.
 *
 * @package Wsal
 */
class WSAL_Sensors_Public extends WSAL_AbstractSensor {

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'user_register', array( $this, 'event_user_register' ) );
	}

	/**
	 * Triggered when a user is registered.
	 *
	 * This function is identical to function WSAL_Sensors_FrontendRegister::event_user_register. We will keep the
	 * duplication until some autoloading improvements are made.
	 *
	 * @param int $user_id - User ID of the registered user.
	 */
	public function event_user_register( $user_id ) {
		$plugin       = WpSecurityAuditLog::GetInstance();
		$user         = get_userdata( $user_id );
		$event        = $plugin->IsMultisite() ? 4012 : ( is_user_logged_in() ? 4001 : 4000 );
		$current_user = wp_get_current_user();

		$new_user_data = array(
			'Username' => $user->user_login,
			'Email'    => $user->user_email,
			'Roles'    => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
		);

		if ( 4000 != $event ) {
			$new_user_data['FirstName'] = ! empty( $user->user_firstname ) ? $user->user_firstname : '';
			$new_user_data['LastName']  = ! empty( $user->user_lastname ) ? $user->user_lastname : '';
		}

		$event_data = array(
			'NewUserID'   => $user_id,
			'UserChanger' => ! empty( $current_user ) ? $current_user->user_login : '',
			'NewUserData' => (object) $new_user_data,
		);

		$plugin->alerts->Trigger( $event, $event_data, true );
	}
}

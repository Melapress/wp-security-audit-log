<?php
/**
 * User registration sensor file.
 *
 * @package    wsal
 * @subpackage sensors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * User registration sensor.
 *
 * 4000 New user was created on WordPress
 *
 * @package    wsal
 * @subpackage sensors
 */
class WSAL_Sensors_Register extends WSAL_AbstractSensor {
	/**
	 * {@inheritDoc}
	 */
	public function hook_events() {
		/*
		 * Default WordPress registration utilizes action 'register_new_user', but we cannot rely on it to detect
		 * a front-end registration implemented by a third party. We hook into the action 'user_register' because it is
		 * part of the function 'wp_insert_user' that definitely runs.
		 */
		add_action( 'user_register', array( $this, 'event_user_register' ), 10, 1 );
	}

	/**
	 * When a user registers, action 'user_register' is fired because it is part of the function 'wp_insert_user'. We
	 * can assume event 4000 if the current session is not logged in.
	 *
	 * @param int $user_id - User ID of the registered user.
	 */
	public function event_user_register( $user_id ) {
		if ( is_user_logged_in() ) {
			// We bail if the user is logged in. That is no longer user registration, but user creation.
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			// Bail if the user is not valid for some reason.
			return;
		}

		$new_user_data = array(
			'Username' => $user->user_login,
			'Roles'    => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
		);

		$event_data = array(
			'NewUserID'    => $user_id,
			'NewUserData'  => (object) $new_user_data,
			'EditUserLink' => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
		);

		if ( $this->is_multisite() ) {
			// Registration should not be logged on multisite if event 4024 is fired.
			$this->plugin->alerts->trigger_event_if(
				4000,
				$event_data,
				/**
				 * Don't log if event 4024 is fired.
				 *
				 * @param WSAL_AlertManager $mgr - Instance of WSAL_AlertManager.
				 */
				function ( WSAL_AlertManager $mgr ) {
					return ! $mgr->will_trigger( 4013 );
				}
			);
		} else {
			$this->plugin->alerts->trigger_event( 4000, $event_data, true );
		}

	}
}

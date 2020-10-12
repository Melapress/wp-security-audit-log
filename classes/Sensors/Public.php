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
	 * Visitor Events.
	 *
	 * @var boolean
	 */
	protected $visitor_events;

	/**
	 * Old Product Stock Quantity.
	 *
	 * @var int
	 */
	protected $_old_stock = null;

	/**
	 * Old Product Stock Status.
	 *
	 * @var string
	 */
	protected $_old_stock_status = null;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		// Hook the events if user is logged in OR if user is not logged in and visitor events are allowed to load.
		if ( is_user_logged_in() ) {
			add_action( 'user_register', array( $this, 'event_user_register' ) );
		}
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
				'NewUserID'    => $user_id,
				'UserChanger'  => ! empty( $current_user ) ? $current_user->user_login : '',
				'NewUserData'  => (object) array(
					'Username'  => $user->user_login,
					'FirstName' => $user->user_firstname,
					'LastName'  => $user->user_lastname,
					'Email'     => $user->user_email,
					'Roles'     => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
				),
				'EditUserLink' => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
			),
			true
		);
	}
}

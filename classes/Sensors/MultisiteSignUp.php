<?php
/**
 * Sensor: Multisite sign-up
 *
 * Multisite sign-up sensor file.
 *
 * @since 4.4.0
 *
 * @package    wsal
 * @subpackage sensors
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multisite sign-up sensor.
 *
 * 4013 User has been activated
 * 4024 User has signed up to the network
 *
 * @package    wsal
 * @subpackage sensors
 */
class WSAL_Sensors_MultisiteSignUp extends WSAL_AbstractSensor {
	/**
	 * {@inheritDoc}
	 */
	public function hook_events() {
		add_action( 'after_signup_user', array( $this, 'handle_multisite_user_signup' ), 10, 4 );
		add_action( 'wpmu_activate_user', array( $this, 'handle_multisite_user_activation' ), 10, 3 );
	}

	/**
	 * Function handles new multisite user activation.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $password User password.
	 * @param array  $meta     Signup meta data.
	 */
	public function handle_multisite_user_activation( $user_id, $password, $meta ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			// Bail if the user is not valid for some reason.
			return;
		}

		$new_user_data = array(
			'Roles'     => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
			'Username'  => $user->user_login,
			'FirstName' => ! empty( $user->user_firstname ) ? $user->user_firstname : '',
			'LastName'  => ! empty( $user->user_lastname ) ? $user->user_lastname : '',
		);

		$event_data = array(
			'NewUserID'    => $user_id,
			'NewUserData'  => (object) $new_user_data,
			'EditUserLink' => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
		);

		$this->plugin->alerts->trigger_event_if( 4013, $event_data, array( $this, 'must_not_contain_create_user' ) );
	}

	/**
	 * Function handles multisite user sign-ups.
	 *
	 * Caution: there is no entry in the users table at this point.
	 *
	 * @param string $user       The user's requested login name.
	 * @param string $user_email The user's email address.
	 * @param string $key        The user's activation key.
	 * @param array  $meta       Signup meta data. Default empty array.
	 */
	public function handle_multisite_user_signup( $user, $user_email, $key, $meta ) {
		$this->plugin->alerts->trigger_event_if(
			4024,
			array(
				'username'      => $user,
				'email_address' => $user_email,
			),
			array( $this, 'must_not_contain_create_user' )
		);
	}

	/**
	 * Callback that checks if event 4012 is already in the pipeline.
	 *
	 * @param WSAL_AlertManager $manager - Instance of WSAL_AlertManager.
	 */
	public function must_not_contain_create_user( WSAL_AlertManager $manager ) {
		return ! $manager->will_trigger( 4012 );
	}
}

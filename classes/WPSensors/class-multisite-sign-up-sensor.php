<?php
/**
 * Sensor: Multisite sign-up
 *
 * Multisite sign-up sensor class file.
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\WP_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Multisite_Sign_Up_Sensor' ) ) {

	/**
	 * Multisite sign-up sensor.
	 *
	 * 4013 User has been activated
	 * 4024 User has signed up to the network
	 *
	 * @package    wsal
	 * @subpackage sensors
	 *
	 * @since 4.5.0
	 */
	class Multisite_Sign_Up_Sensor {

		/**
		 * Is that a login sensor or not?
		 * Sensors doesn't need to have this property, except where they explicitly have to set that value.
		 *
		 * @var boolean
		 *
		 * @since 4.5.0
		 */
		private static $frontend_sensor = true;

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			if ( WP_Helper::is_multisite() ) {
				add_action( 'after_signup_user', array( __CLASS__, 'handle_multisite_user_signup' ), 10, 4 );
				add_action( 'wpmu_activate_user', array( __CLASS__, 'handle_multisite_user_activation' ), 10, 3 );
			}
		}

		/**
		 * Is that a front end sensor? The sensors doesn't need to have that method implemented, except if they want to specifically set that value.
		 *
		 * @return boolean
		 *
		 * @since 4.5.0
		 */
		public static function is_frontend_sensor() {
			return self::$frontend_sensor;
		}

		/**
		 * Function handles new multisite user activation.
		 *
		 * @param int    $user_id  User ID.
		 * @param string $password User password.
		 * @param array  $meta     Signup meta data.
		 *
		 * @since 4.5.0
		 */
		public static function handle_multisite_user_activation( $user_id, $password, $meta ) {
			$user = get_userdata( $user_id );
			if ( ! $user instanceof \WP_User ) {
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
				'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
			);

			Alert_Manager::trigger_event_if( 4013, $event_data, array( __CLASS__, 'must_not_contain_create_user' ) );
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
		 *
		 * @since 4.5.0
		 */
		public static function handle_multisite_user_signup( $user, $user_email, $key, $meta ) {
			Alert_Manager::trigger_event_if(
				4024,
				array(
					'username'      => $user,
					'email_address' => $user_email,
				),
				array( __CLASS__, 'must_not_contain_create_user' )
			);
		}

		/**
		 * Callback that checks if event 4012 is already in the pipeline.
		 *
		 * @since 4.5.0
		 */
		public static function must_not_contain_create_user() {
			return ! Alert_Manager::will_trigger( 4012 );
		}
	}
}

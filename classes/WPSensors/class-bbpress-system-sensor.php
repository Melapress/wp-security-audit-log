<?php
/**
 * Sensor: System Activity.
 *
 * System activity sensor class file.
 *
 * @since 1.0.0
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\BBPress_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\BBPress_System_Sensor' ) ) {
	/**
	 * System Activity sensor.
	 *
	 * 8009 User changed forum's role
	 * 8010 User changed option of a forum
	 * 8012 User changed time to disallow post editing
	 * 8013 User changed the forum setting posting throttle time
	 *
	 * @package Wsal
	 * @subpackage Sensors
	 */
	class BBPress_System_Sensor {
		/**
		 * Listening to events using WP hooks.
		 *
		 * @since 4.6.0
		 */
		public static function init() {
			if ( BBPress_Helper::is_bbpress_active() ) {
				add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );
			}
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.6.0
		 */
		public static function event_admin_init() {
			// Filter global arrays for security.
			$post_array   = filter_input_array( INPUT_POST );
			$server_array = filter_input_array( INPUT_SERVER );

			// Make sure user can actually modify target options.
			if ( ! current_user_can( 'manage_options' ) && isset( $post_array['_wpnonce'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'update' ) ) {
				return;
			}

			$actype = '';
			if ( ! empty( $server_array['SCRIPT_NAME'] ) ) {
				$actype = basename( $server_array['SCRIPT_NAME'], '.php' );
			}

			$is_option_page      = 'options' === $actype;
			$is_network_settings = 'settings' === $actype;
			$is_permalink_page   = 'options-permalink' === $actype;

			/* BBPress Forum support  Setting */
			if ( isset( $post_array['action'] ) && 'update' === $post_array['action'] && isset( $post_array['_bbp_default_role'] ) ) {
				$old_role = get_option( '_bbp_default_role' );
			$get_array    = filter_input_array( INPUT_GET );
				$new_role = \sanitize_text_field( \wp_unslash( $post_array['_bbp_default_role'] ) );
				if ( $old_role !== $new_role ) {
					Alert_Manager::trigger_event(
						8009,
						array(
							'OldRole' => $old_role,
							'NewRole' => $new_role,
						)
					);
				}
			}

			if ( isset( $post_array['action'] ) && 'update' === $post_array['action'] && isset( $post_array['option_page'] ) && ( 'bbpress' === $post_array['option_page'] ) ) {
				// Anonymous posting.
				$allow_anonymous = get_option( '_bbp_allow_anonymous' );
				$old_status      = ! empty( $allow_anonymous ) ? 1 : 0;
				$new_status      = ! empty( $post_array['_bbp_allow_anonymous'] ) ? 1 : 0;

				if ( $old_status !== $new_status ) {
					Alert_Manager::trigger_event(
						8010,
						array( 'EventType' => ( 1 === $new_status ) ? 'enabled' : 'disabled' )
					);
				}

				// Disallow editing after.
				$bbp_edit_lock = get_option( '_bbp_edit_lock' );
				$old_time      = ! empty( $bbp_edit_lock ) ? intval( $bbp_edit_lock ) : '';
				$new_time      = ! empty( $post_array['_bbp_edit_lock'] ) ? intval( $post_array['_bbp_edit_lock'] ) : '';
				if ( $old_time !== $new_time ) {
					Alert_Manager::trigger_event(
						8012,
						array(
							'OldTime' => $old_time,
							'NewTime' => $new_time,
						)
					);
				}

				// Throttle posting every.
				$bbp_throttle_time = get_option( '_bbp_throttle_time' );
				$old_time2         = ! empty( $bbp_throttle_time ) ? intval( $bbp_throttle_time ) : '';
				$new_time2         = ! empty( $post_array['_bbp_throttle_time'] ) ? intval( $post_array['_bbp_throttle_time'] ) : '';
				if ( $old_time2 !== $new_time2 ) {
					Alert_Manager::trigger_event(
						8013,
						array(
							'OldTime' => $old_time2,
							'NewTime' => $new_time2,
						)
					);
				}
			}
		}
	}
}

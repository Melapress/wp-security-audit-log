<?php
/**
 * Sensor: Termly Activity.
 *
 * Termly activity sensor class file.
 *
 * @since 5.4.0
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\Settings_Helper;
use WSAL\WP_Sensors\Helpers\Termly_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Termly_System_Sensor' ) ) {
	/**
	 * System Activity sensor.
	 *
	 * 10901 Site has been disconnected from Termly.
	 * 8010 User changed option of a forum
	 * 8012 User changed time to disallow post editing
	 * 8013 User changed the forum setting posting throttle time
	 *
	 * @package Wsal
	 * @subpackage Sensors
	 */
	class Termly_System_Sensor {
		/**
		 * Listening to events using WP hooks.
		 *
		 * @since 5.4.0
		 */
		public static function init() {
			if ( Termly_Helper::is_termly_active() ) {
				\add_action( 'update_option_termly_api_key', array( __CLASS__, 'udpated_api' ), 10, 2 );
				\add_action( 'add_option_termly_api_key', array( __CLASS__, 'added_api' ), 10, 2 );
				\add_action( 'delete_option_termly_api_key', array( __CLASS__, 'disconnect' ) );
				\add_action( 'update_option_termly_site_scan', array( __CLASS__, 'site_scan' ), 10, 2 );
				\add_action( 'update_option_termly_display_banner', array( __CLASS__, 'banner_settings' ), 10, 2 );
				\add_action( 'add_option_termly_display_banner', array( __CLASS__, 'banner_settings_add' ), 10, 2 );
				\add_action( 'add_option_termly_site_scan', array( __CLASS__, 'site_scan_add' ), 10, 2 );

				\add_action( 'update_option_termly_display_auto_blocker', array( __CLASS__, 'auto_blocker' ), 10, 2 );
				\add_action( 'add_option_termly_display_auto_blocker', array( __CLASS__, 'auto_blocker_add' ), 10, 2 );
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function early_init() {
			\add_filter(
				'wsal_event_objects',
				array( Termly_Helper::class, 'wsal_termly_add_custom_event_objects' ),
				10,
				2
			);
		}

		/**
		 * Triggers the disconnect event.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function disconnect() {

			Alert_Manager::trigger_event(
				10901,
				array()
			);
		}

		/**
		 * Fires on the timely termly_site_scan option update.
		 *
		 * @param mixed $old_value - Old value of the option.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function site_scan( $old_value, $new_value ) {
			if ( \is_array( $new_value ) ) {
				if ( isset( $new_value['enabled'] ) ) {
					$event_type = Settings_Helper::string_to_bool( $new_value['enabled'] ) ? 'enabled' : 'disabled';
				} else {
					$event_type = 'disabled';
				}
				if ( isset( $new_value['frequency'] ) ) {
					$frequency = $new_value['frequency'];
				} else {
					$frequency = 'trimonthly';
				}
				if ( isset( $new_value['robots_txt'] ) ) {
					$robots_txt = Settings_Helper::string_to_bool( $new_value['robots_txt'] ) ? 'enabled' : 'disabled';
				} else {
					$robots_txt = 'disabled';
				}
			} else {
				$event_type = 'disabled';
				$frequency  = 'trimonthly';
				$robots_txt = 'disabled';
			}
			if ( \is_array( $old_value ) ) {
				if ( isset( $old_value['enabled'] ) ) {
					$previous_value = Settings_Helper::string_to_bool( $old_value['enabled'] ) ? 'enabled' : 'disabled';
				} else {
					$previous_value = 'disabled';
				}
				if ( isset( $old_value['frequency'] ) ) {
					$previous_frequency = $old_value['frequency'];
				} else {
					$previous_frequency = 'trimonthly';
				}
				if ( isset( $old_value['robots_txt'] ) ) {
					$previous_robots_txt = Settings_Helper::string_to_bool( $old_value['robots_txt'] ) ? 'enabled' : 'disabled';
				} else {
					$previous_robots_txt = 'disabled';
				}
			} else {
				$previous_value      = 'disabled';
				$previous_frequency  = 'trimonthly';
				$previous_robots_txt = 'disabled';
			}

			if ( $event_type !== $previous_value ) {
				Alert_Manager::trigger_event(
					10902,
					array(
						'PreviousValue' => $previous_value,
						'EventType'     => $event_type,
					)
				);
			}
			// if ( $frequency !== $previous_frequency ) {
			// Alert_Manager::trigger_event(
			// 10902,
			// array(
			// 'PreviousValue' => $previous_value,
			// 'EventType'     => $event_type,
			// )
			// );
			// }
			if ( $robots_txt !== $previous_robots_txt ) {
				Alert_Manager::trigger_event(
					10903,
					array(
						'PreviousValue' => $previous_robots_txt,
						'EventType'     => $robots_txt,
					)
				);
			}
		}

		/**
		 * Fires on the timely termly_site_scan option add.
		 *
		 * @param mixed $option    - Option name.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function site_scan_add( $option, $new_value ) {
			if ( \is_array( $new_value ) ) {
				if ( isset( $new_value['enabled'] ) ) {
					$event_type = Settings_Helper::string_to_bool( $new_value['enabled'] ) ? 'enabled' : 'disabled';
				} else {
					$event_type = 'disabled';
				}
				if ( isset( $new_value['frequency'] ) ) {
					$frequency = $new_value['frequency'];
				} else {
					$frequency = 'trimonthly';
				}
				if ( isset( $new_value['robots_txt'] ) ) {
					$robots_txt = Settings_Helper::string_to_bool( $new_value['robots_txt'] ) ? 'enabled' : 'disabled';
				} else {
					$robots_txt = 'disabled';
				}
			} else {
				$event_type = 'disabled';
				$frequency  = 'trimonthly';
				$robots_txt = 'disabled';
			}

			Alert_Manager::trigger_event(
				10902,
				array(
					'PreviousValue' => 'disabled',
					'EventType'     => $event_type,
				)
			);

			// if ( $frequency !== $previous_frequency ) {
			// Alert_Manager::trigger_event(
			// 10902,
			// array(
			// 'PreviousValue' => $previous_value,
			// 'EventType'     => $event_type,
			// )
			// );
			// }
			Alert_Manager::trigger_event(
				10903,
				array(
					'PreviousValue' => 'disabled',
					'EventType'     => $robots_txt,
				)
			);
		}

		/**
		 * Fires on the timely termly_site_scan option update.
		 *
		 * @param mixed $old_value - Old value of the option.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function banner_settings( $old_value, $new_value ) {

			$old_value = Settings_Helper::string_to_bool( $old_value ) ? 'enabled' : 'disabled';
			$new_value = Settings_Helper::string_to_bool( $new_value ) ? 'enabled' : 'disabled';

			if ( $old_value !== $new_value ) {
				Alert_Manager::trigger_event(
					10904,
					array(
						'PreviousValue' => $old_value,
						'EventType'     => $new_value,
					)
				);
			}
		}

		/**
		 * Fires on the timely termly_site_scan option add.
		 *
		 * @param mixed $option    - Option name.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function banner_settings_add( $option, $new_value ) {

			$new_value = Settings_Helper::string_to_bool( $new_value ) ? 'enabled' : 'disabled';

			Alert_Manager::trigger_event(
				10904,
				array(
					'PreviousValue' => 'disabled',
					'EventType'     => $new_value,
				)
			);
		}

		/**
		 * Fires on the timely termly_site_scan option update.
		 *
		 * @param mixed $old_value - Old value of the option.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function auto_blocker( $old_value, $new_value ) {

			$old_value = Settings_Helper::string_to_bool( $old_value ) ? 'enabled' : 'disabled';
			$new_value = Settings_Helper::string_to_bool( $new_value ) ? 'enabled' : 'disabled';

			if ( $old_value !== $new_value ) {
				Alert_Manager::trigger_event(
					10905,
					array(
						'PreviousValue' => $old_value,
						'EventType'     => $new_value,
					)
				);
			}
		}

		/**
		 * Fires on the timely termly_site_scan option add.
		 *
		 * @param mixed $option    - Option name.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function auto_blocker_add( $option, $new_value ) {

			$new_value = Settings_Helper::string_to_bool( $new_value ) ? 'enabled' : 'disabled';

			Alert_Manager::trigger_event(
				10905,
				array(
					'PreviousValue' => 'disabled',
					'EventType'     => $new_value,
				)
			);
		}
		/**
		 * Fires on the timely termly_site_scan option update.
		 *
		 * @param mixed $old_value - Old value of the option.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function udpated_api( $old_value, $new_value ) {

			if ( $old_value !== $new_value ) {
				Alert_Manager::trigger_event(
					10911,
					array(
						'PreviousValue' => $old_value,
						'NewValue'      => $new_value,
					)
				);
			}
		}

		/**
		 * Fires on the timely termly_site_scan option add.
		 *
		 * @param mixed $option    - Option name.
		 * @param mixed $new_value - New value of the option.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function added_api( $option, $new_value ) {

			Alert_Manager::trigger_event(
				10911,
				array()
			);
		}
	}
}

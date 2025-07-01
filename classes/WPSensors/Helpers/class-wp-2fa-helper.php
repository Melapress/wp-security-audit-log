<?php
/**
 * YOAST Sensor helper
 *
 * @since     5.0.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\WP_2FA_Helper' ) ) {

	/**
	 * Helper Sensor class for YOAST.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since      5.0.0
	 */
	class WP_2FA_Helper {

		/**
		 * Class cache to store the state of the plugin.
		 *
		 * @var bool
		 *
		 * @since 5.3.4.1
		 */
		private static $plugin_active = null;

		/**
		 * Class cache to store the state of the plugin for sensors.
		 *
		 * @var bool
		 *
		 * @since 5.3.4.1
		 */
		private static $plugin_active_for_sensors = null;

		/**
		 * Adds new custom event objects for our plugin
		 *
		 * @method add_custom_event_objects
		 * @param  array $objects An array of default objects.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function add_custom_event_objects( $objects ) {
			$new_objects = array(
				'wp-2fa-settings' => esc_html__( 'WP 2FA Settings', 'wp-security-audit-log' ),
				'2fa'             => esc_html__( '2FA', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );
			return $objects;
		}

		/**
		 * Checks if the WP 2FA is active.
		 *
		 * @return boolean - Is plugin active or not.
		 *
		 * @since 5.0.0
		 */
		public static function is_wp2fa_active() {
			if ( null === self::$plugin_active ) {

				if ( defined( 'WP_2FA_VERSION' ) ) {
					self::$plugin_active = true;
				} else {
					self::$plugin_active = false;
				}
			}

			return self::$plugin_active;
		}

		/**
		 * Shall we load custom alerts for sensors?
		 *
		 * @return boolean
		 *
		 * @since 5.3.4.1
		 */
		public static function load_alerts_for_sensor(): bool {
			if ( null === self::$plugin_active_for_sensors ) {
				self::$plugin_active_for_sensors = ( WP_Helper::is_plugin_active( 'wp-2fa/wp-2fa.php' ) || WP_Helper::is_plugin_active( 'wp-2fa-premium/wp-2fa.php' ) );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string - Plugin filename.
		 *
		 * @since 5.0.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-2fa/wp-2fa.php';
		}
	}
}

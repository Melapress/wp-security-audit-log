<?php
/**
 * Redirection Sensor helper.
 *
 * @since 5.1.0
 *
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

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\Redirection_Helper' ) ) {
	/**
	 * Helper Sensor class for Redirection.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since 5.1.0
	 */
	class Redirection_Helper {

		/**
		 * Class cache to store the state of the plugin.
		 *
		 * @var bool
		 *
		 * @since 5.3.0
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
		 * Register a custom event object within WSAL.
		 *
		 * @param array $objects array of objects current registered within WSAL.
		 *
		 * @since 5.1.0
		 */
		public static function wsal_redirection_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'redirection' => esc_html__( 'Redirection', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Checks if the Redirection is active.
		 *
		 * @return bool
		 *
		 * @since 5.1.0
		 */
		public static function is_redirection_active() {
			if ( null === self::$plugin_active ) {
				// self::$plugin_active = WP_Helper::is_plugin_active( 'redirection/redirection.php' );

				// if ( WP_Helper::is_multisite() ) {
					// Check if the plugin is active on the main site.
				if ( defined( 'REDIRECTION_DB_VERSION' ) ) {
					// Plugin is enabled, run your code...
					self::$plugin_active = true;
				} else {
					self::$plugin_active = false;
				}
				// }
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
				self::$plugin_active_for_sensors = WP_Helper::is_plugin_active( 'redirection/redirection.php' );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Added our event types to the available list.
		 *
		 * @param  array $types - Current event types.
		 *
		 * @return array $types - Altered list.
		 *
		 * @since 5.1.0
		 */
		public static function wsal_redirection_add_custom_event_type( $types ) {
			$new_types = array(
				'reset' => esc_html__( 'Reset', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}
	}
}

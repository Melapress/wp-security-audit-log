<?php
/**
 * Tablepress Sensor helper.
 *
 * @since     4.6.0
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

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\TablePress_Helper' ) ) {
	/**
	 * Helper Sensor class for Tablepress.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since      4.6.0
	 */
	class TablePress_Helper {

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
		 * @since 4.6.0
		 */
		public static function wsal_tablepress_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'tablepress_tables' => esc_html__( 'TablePress', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Adds custom event type
		 *
		 * @param array $types - Array of event types.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_tablepress_add_custom_event_type( $types ) {
			$new_types = array(
				'imported' => __( 'Imported', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}

		/**
		 * Adds new ignored CPT for our plugin.
		 *
		 * @method wsal_tablepress_add_custom_ignored_cpt
		 *
		 * @since  1.0.0
		 *
		 * @param array $post_types An array of default post_types.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_tablepress_add_custom_ignored_cpt( $post_types ) {
			$new_post_types = array(
				'tablepress_table',
			);

			// combine the two arrays.
			$post_types = array_merge( $post_types, $new_post_types );

			return $post_types;
		}

		/**
		 * Checks if the Tablepress is active.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function is_tablepress_active() {
			if ( null === self::$plugin_active ) {
				// self::$plugin_active = WP_Helper::is_plugin_active( 'tablepress/tablepress.php' );

				// if ( WP_Helper::is_multisite() ) {
					// Check if the plugin is active on the main site.
				if ( defined( 'TABLEPRESS_ABSPATH' ) ) {
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
				self::$plugin_active_for_sensors = WP_Helper::is_plugin_active( 'tablepress/tablepress.php' );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Returns a list of custom post types associated with particular extension.
		 *
		 * @return array List of custom post types.
		 *
		 * @since 4.5.0
		 */
		public static function get_custom_post_types(): array {
			return array( 'tablepress_table' );
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-tablepress/wsal-tablepress.php';
		}
	}
}

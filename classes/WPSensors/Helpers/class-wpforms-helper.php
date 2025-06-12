<?php
/**
 * WPForms Sensor helper
 *
 * @since     4.6.0
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

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\WPForms_Helper' ) ) {

	/**
	 * Helper Sensor class for WPForms.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 * @since      4.6.0
	 */
	class WPForms_Helper {

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
		 * @method wsal_wpforms_add_custom_event_objects
		 * @since 4.6.0
		 * @param  array $objects An array of default objects.
		 * @return array
		 */
		public static function wsal_wpforms_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'wpforms'               => esc_html__( 'WPForms', 'wp-security-audit-log' ),
				'wpforms_notifications' => esc_html__( 'Notifications in WPForms', 'wp-security-audit-log' ),
				'wpforms_entries'       => esc_html__( 'Entries in WPForms', 'wp-security-audit-log' ),
				'wpforms_fields'        => esc_html__( 'Fields in WPForms', 'wp-security-audit-log' ),
				'wpforms_forms'         => esc_html__( 'Forms in WPForms', 'wp-security-audit-log' ),
				'wpforms_confirmations' => esc_html__( 'Confirmations in WPForms', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Adds new ignored CPT for our plugin
		 *
		 * @method wsal_wpforms_add_custom_ignored_cpt
		 * @since 4.6.0
		 * @param  array $post_types An array of default post_types.
		 * @return array
		 */
		public static function wsal_wpforms_add_custom_ignored_cpt( $post_types ) {
			$new_post_types = array(
				'wpforms',    // WP Forms CPT.
			);

			// combine the two arrays.
			$post_types = array_merge( $post_types, $new_post_types );
			return $post_types;
		}

		/**
		 * Checks if the WPForms is active.
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_wpforms_active() {
			if ( null === self::$plugin_active ) {
				// self::$plugin_active = ( WP_Helper::is_plugin_active( 'wpforms-premium/wpforms.php' ) || WP_Helper::is_plugin_active( 'wpforms/wpforms.php' ) || WP_Helper::is_plugin_active( 'wpforms-lite/wpforms.php' ) );

				// if ( WP_Helper::is_multisite() ) {
					// Check if WooCommerce is active on the current site.

				if ( defined( 'WPFORMS_VERSION' ) ) {
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
				self::$plugin_active_for_sensors = ( WP_Helper::is_plugin_active( 'wpforms-premium/wpforms.php' ) || WP_Helper::is_plugin_active( 'wpforms/wpforms.php' ) || WP_Helper::is_plugin_active( 'wpforms-lite/wpforms.php' ) );
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
			return array( 'wpforms' );
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-security-audit-log-add-on-for-wpforms/wsal-wpforms.php';
		}
	}
}

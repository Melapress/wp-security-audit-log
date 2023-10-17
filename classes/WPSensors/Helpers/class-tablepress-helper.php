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
			return WP_Helper::is_plugin_active( 'tablepress/tablepress.php' );
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

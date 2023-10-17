<?php
/**
 * BBPress Sensor helper.
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

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\BBPress_Helper' ) ) {
	/**
	 * Helper Sensor class for YOAST.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since      4.6.0
	 */
	class BBPress_Helper {
		/**
		 * Adds new custom event objects for our plugin.
		 *
		 * @method wsal_bbpress_add_custom_event_objects
		 *
		 * @since 4.6.0
		 *
		 * @param array $objects An array of default objects.
		 *
		 * @return array
		 */
		public static function wsal_bbpress_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'bbpress'       => __( 'BBPress', 'wp-security-audit-log' ),
				'bbpress-forum' => __( 'BBPress Forum', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Add obsolete events to the togglealerts view.
		 *
		 * @param array $obsolete_events = An array of currently obsolete events.
		 *
		 * @return array $obsolete_events - Appended array.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_bbpress_extension_togglealerts_obsolete_events( $obsolete_events ) {
			$new_events      = array(
				4013,
			);
			$obsolete_events = array_merge( $obsolete_events, $new_events );

			return $obsolete_events;
		}

		/**
		 * Adds new ignored CPT for our plugin.
		 *
		 * @method wsal_woocommerce_extension_add_custom_event_object_text
		 *
		 * @since 4.6.0
		 *
		 * @param array $post_types An array of default post_types.
		 *
		 * @return array
		 */
		public static function wsal_bbpress_extension_add_custom_ignored_cpt( $post_types ) {
			$new_post_types = array(
				'forum',
				'topic',
				'reply',
			);

			// combine the two arrays.
			$post_types = array_merge( $post_types, $new_post_types );

			return $post_types;
		}

		/**
		 * Checks if the BBPress is active.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function is_bbpress_active() {
			return WP_Helper::is_plugin_active( 'bbpress/bbpress.php' );
		}

		/**
		 * Returns a list of custom post types associated with particular extension.
		 *
		 * @return array List of custom post types.
		 *
		 * @since 4.5.0
		 */
		public static function get_custom_post_types(): array {
			return array( 'forum', 'topic', 'reply' );
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-security-audit-log-add-on-for-bbpress/wsal-bbpress.php';
		}
	}
}

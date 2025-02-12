<?php
/**
 * Tablepress Sensor helper.
 *
 * @since     5.0.0
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

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\ACF_Helper' ) ) {

	/**
	 * Helper Sensor class for Tablepress.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since      5.0.0
	 */
	class ACF_Helper {

		/**
		 * Class cache to store the state of the plugin.
		 *
		 * @var bool
		 *
		 * @since 5.3.0
		 */
		private static $plugin_active = null;

		/**
		 * Register a custom event object within WSAL.
		 *
		 * @param array $objects array of objects current registered within WSAL.
		 * @return array $objects - Modified array.
		 *
		 * @since 5.0.0
		 */
		public static function add_custom_event_objects( $objects ) {
			$new_objects = array(
				'acf-config-post-types' => esc_html__( 'ACF configuration - Post types', 'wp-security-audit-log' ),
				'acf-config-taxonomies' => esc_html__( 'ACF configuration - Taxonomies', 'wp-security-audit-log' ),
				'acf-config-terms'      => esc_html__( 'ACF - Taxonomy terms', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Add our items to the ignored list.
		 *
		 * @param  array $post_types - Current WSAL ignored list.
		 * @return array $post_types - Modified array.
		 *
		 * @since 5.0.0
		 */
		public static function add_custom_ignored_cpt( $post_types ) {
			$new_post_types = array(
				'acf-post-type',
				'acf-taxonomy',
			);

			// combine the two arrays.
			$post_types = array_merge( $post_types, $new_post_types );

			return $post_types;
		}

		/**
		 * Checks if the ACF is active.
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function is_acf_active() {
			if ( null === self::$plugin_active ) {
				self::$plugin_active = WP_Helper::is_plugin_active( 'advanced-custom-fields/acf.php' );
			}

			return self::$plugin_active;
		}
	}
}

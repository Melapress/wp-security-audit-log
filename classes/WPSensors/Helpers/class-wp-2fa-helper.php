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
			return ( WP_Helper::is_plugin_active( 'wp-2fa/wp-2fa.php' ) || WP_Helper::is_plugin_active( 'wp-2fa-premium/wp-2fa.php' ) );
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

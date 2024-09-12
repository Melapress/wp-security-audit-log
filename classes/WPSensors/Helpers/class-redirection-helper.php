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
			return WP_Helper::is_plugin_active( 'redirection/redirection.php' );
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
				'reset'   => esc_html__( 'Reset', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}
	}
}
